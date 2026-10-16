<?php

namespace App\Support;

use App\Models\AdminActivity;
use App\Models\SiteText as SiteTextRow;
use App\Models\SiteTextRevision;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Mode CMS: admin overrides for the marketing copy wrapped in `<x-cms key="...">`. The Blade slot is the
 * default; an override replaces it until it is restored. Overrides are sanitized here before they are
 * stored, so views print them as-is.
 */
class SiteText
{
    /** Every editable key. A test keeps this list equal to the `<x-cms key="...">` used in resources/views. */
    public const KEYS = [
        // Beranda (home.blade.php)
        'home.hero.title',
        'home.hero.sub',
        'home.how.title',
        'home.how.lead',
        'home.why.title',
        'home.why.co.1.title',
        'home.why.co.1.desc',
        'home.why.co.2.title',
        'home.why.co.2.desc',
        'home.why.co.3.title',
        'home.why.co.3.desc',
        'home.why.ta.1.title',
        'home.why.ta.1.desc',
        'home.why.ta.2.title',
        'home.why.ta.2.desc',
        'home.why.ta.3.title',
        'home.why.ta.3.desc',
        'home.story.quote',
        'home.story.body',
        'home.final.co.title',
        'home.final.co.desc',
        'home.final.ta.title',
        'home.final.ta.desc',
        // Tentang Kami (about.blade.php)
        'about.hero.title',
        'about.hero.lead',
        'about.why.title',
        'about.why.p1',
        'about.why.p2',
        'about.why.quote',
        'about.why.p3',
        'about.why.note',
        'about.values.title',
        'about.values.1.title',
        'about.values.1.desc',
        'about.values.2.title',
        'about.values.2.desc',
        'about.values.3.title',
        'about.values.3.desc',
        // Untuk Talenta (for-talents.blade.php)
        'talents.hero.title',
        'talents.hero.lead',
        'talents.points.1.title',
        'talents.points.1.desc',
        'talents.points.2.title',
        'talents.points.2.desc',
        'talents.points.3.title',
        'talents.points.3.desc',
        'talents.points.4.title',
        'talents.points.4.desc',
        'talents.flow.title',
        // Untuk Perusahaan (companies.blade.php)
        'companies.hero.title',
        'companies.hero.lead',
        'companies.points.1.title',
        'companies.points.1.desc',
        'companies.points.2.title',
        'companies.points.2.desc',
        'companies.points.3.title',
        'companies.points.3.desc',
        'companies.points.4.title',
        'companies.points.4.desc',
        'companies.price.title',
        'companies.price.lead',
        'companies.price.free',
        'companies.flow.title',
        // Kontak (contact.blade.php)
        'contact.title',
        'contact.lead',
        'contact.address',
    ];

    public const MAX_LENGTH = 2000;

    /** Cached overrides live under site_texts:<generation>; a write moves to a new generation. */
    private const CACHE_KEY = 'site_texts';

    private const CACHE_GENERATION = 'site_texts:generation';

    /** Safety net: even if an invalidation were lost, a stale copy lives at most this long. */
    private const CACHE_TTL = 600;

    /** Tags kept as they are. `a` and `br` are handled on their own. */
    private const INLINE = ['b', 'strong', 'i', 'em'];

    /** Dropped together with everything inside them. Any other tag is unwrapped (its text stays). */
    private const DROP = ['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'textarea', 'select', 'button'];

    /** Block tags that become a line break when unwrapped (contenteditable wraps new lines in them). */
    private const BLOCK = ['div', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'tr'];

    /**
     * Overrides keyed by key: ['html' => ?string, 'version' => int]. Memoized, so one cache read per request.
     * A reader that loaded old rows while a write committed stores them under the old generation, which
     * nobody reads any more, so a race cannot keep stale text cached.
     */
    public static function all(): array
    {
        $generation = Cache::memo()->rememberForever(self::CACHE_GENERATION, fn () => Str::random(12));

        return Cache::memo()->remember(self::CACHE_KEY.':'.$generation, self::CACHE_TTL, fn () => SiteTextRow::query()
            ->get(['key', 'html', 'version'])
            ->mapWithKeys(fn (SiteTextRow $row) => [$row->key => ['html' => $row->html, 'version' => (int) $row->version]])
            ->all());
    }

    /** The stored (already sanitized) override, or null to show the default. */
    public static function get(string $key): ?string
    {
        return self::all()[$key]['html'] ?? null;
    }

    /** The version the editor saw, sent back as `expected` on save. 0 while no row exists yet. */
    public static function version(string $key): int
    {
        return self::all()[$key]['version'] ?? 0;
    }

    public static function isKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /** True for an admin who switched on Mode edit (POST /cms/mode). */
    public static function editing(): bool
    {
        return (bool) (auth()->user()?->isAdmin() && session('cms_edit'));
    }

    /**
     * Allowlist sanitizer built on DOMDocument: keeps b, strong, i, em, br and a (http, https, mailto or a
     * relative path; rel set by the server, target and every other attribute dropped). Other tags are
     * unwrapped, script/style and similar are removed with their content. The output is built from
     * scratch, never by re-serializing the parsed input.
     */
    public static function sanitize(string $html): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><body><div>'.$html.'</div></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // Walk the whole body: a stray closing tag in the input cannot push text outside the walk.
        $root = $doc->getElementsByTagName('body')->item(0) ?? $doc;
        $out = self::walk($root, false);

        // Trim leading/trailing line breaks and whitespace that came from unwrapped blocks.
        return preg_replace('/^(\s|<br>)+|(\s|<br>)+$/u', '', $out) ?? '';
    }

    /** Visible characters of sanitized html (tags do not count). */
    public static function visibleLength(string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Save an override. $expected is the version the editor saw (0 or null = the default, with no row yet). */
    public static function save(string $key, string $html, User $user, ?int $expected): SiteTextRow
    {
        self::assertKey($key);
        $clean = self::sanitize($html);
        $length = self::visibleLength($clean);
        if ($length === 0) {
            throw ValidationException::withMessages(['html' => 'Teks tidak boleh kosong.']);
        }
        if ($length > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['html' => 'Teks maksimal '.self::MAX_LENGTH.' karakter.']);
        }

        return self::locked(function () use ($key, $clean, $user, $expected) {
            $row = SiteTextRow::where('key', $key)->lockForUpdate()->first();
            if ((int) $row?->version !== (int) $expected) {
                throw self::conflict();
            }
            if ($row?->html === $clean) {
                return $row;
            }

            return self::write($key, $row, $clean, $user, 'text_edited', 'Teks "'.$key.'" diubah: '.self::snippet($clean, 160));
        });
    }

    /** Restore a revision of this key, or the default when $revisionId is null. Returns null if nothing changed. */
    public static function restore(string $key, ?int $revisionId, User $user): ?SiteTextRow
    {
        self::assertKey($key);

        return self::locked(function () use ($key, $revisionId, $user) {
            $html = $revisionId === null ? null : SiteTextRevision::where('key', $key)->findOrFail($revisionId)->html;
            $row = SiteTextRow::where('key', $key)->lockForUpdate()->first();
            if (($row?->html) === $html) {
                return $row;
            }

            return self::write($key, $row, $html, $user, 'text_restored', $html === null
                ? 'Teks "'.$key.'" dikembalikan ke teks asli.'
                : 'Teks "'.$key.'" dipulihkan ke versi sebelumnya: '.self::snippet($html, 160));
        });
    }

    /** Plain-text preview of sanitized html, for history lists and the activity log. */
    public static function snippet(?string $html, int $limit = 120): ?string
    {
        if ($html === null) {
            return null;
        }
        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit(trim($text), $limit);
    }

    /** The row is never deleted, so the activity log always has a subject; html null means the default. */
    private static function write(string $key, ?SiteTextRow $row, ?string $html, User $user, string $action, string $description): SiteTextRow
    {
        $row ??= new SiteTextRow(['key' => $key]);
        SiteTextRevision::create(['key' => $key, 'html' => $row->html, 'user_id' => $user->id]);
        $row->fill(['html' => $html, 'version' => (int) $row->version + 1, 'updated_by' => $user->id])->save();
        AdminActivity::record($action, $row, $description, $user->id);

        // Only once the write is visible to other requests: start a new cache generation.
        DB::afterCommit(fn () => Cache::memo()->forever(self::CACHE_GENERATION, Str::random(12)));

        return $row;
    }

    /**
     * Run a write under a row lock. Lock waits and deadlocks are retried (3 attempts); if they keep
     * failing, the editor gets the same 409 as a version conflict instead of a 500.
     */
    private static function locked(\Closure $write): ?SiteTextRow
    {
        try {
            return DB::transaction($write, 3);
        } catch (QueryException|DeadlockException $e) {
            if ($e instanceof DeadlockException || self::concurrency()->check($e)) {
                throw self::conflict();
            }
            throw $e;
        }
    }

    private static function conflict(): ConflictHttpException
    {
        return new ConflictHttpException('Teks sudah diubah admin lain, muat ulang.');
    }

    /** Laravel's own deadlock/lock-timeout detection (the trait method is protected). */
    private static function concurrency(): object
    {
        return new class
        {
            use DetectsConcurrencyErrors;

            public function check(\Throwable $e): bool
            {
                return $this->causedByConcurrencyError($e);
            }
        };
    }

    private static function assertKey(string $key): void
    {
        if (! self::isKey($key)) {
            throw ValidationException::withMessages(['key' => 'Teks ini tidak bisa diedit.']);
        }
    }

    private static function walk(DOMNode $node, bool $inLink): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // Non-breaking spaces from contenteditable become normal spaces.
                $out .= htmlspecialchars(str_replace("\u{00A0}", ' ', $child->nodeValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue; // comments, processing instructions
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                continue;
            }
            if ($tag === 'br') {
                $out .= '<br>';
            } elseif (in_array($tag, self::INLINE, true)) {
                $out .= '<'.$tag.'>'.self::walk($child, $inLink).'</'.$tag.'>';
            } elseif ($tag === 'a' && ! $inLink && ($href = self::safeHref($child->getAttribute('href'))) !== null) {
                $out .= '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" rel="noopener noreferrer">'.self::walk($child, true).'</a>';
            } else {
                $inner = self::walk($child, $inLink);
                if (in_array($tag, self::BLOCK, true) && $out !== '' && ! str_ends_with($out, '<br>')) {
                    $out .= '<br>';
                }
                $out .= $inner;
            }
        }

        return $out;
    }

    /** http(s), mailto, or a same-site relative path / anchor. Anything else (javascript:, data:, //host) is null. */
    private static function safeHref(string $href): ?string
    {
        $href = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $href) ?? '');
        $compact = preg_replace('/\s+/', '', $href) ?? '';
        // Browsers read "\" as "/" in URLs ("/\evil.com" is another host), so refuse it anywhere.
        if ($href === '' || str_starts_with($compact, '//') || str_contains($compact, '\\')) {
            return null;
        }
        if (preg_match('#^(https?://|mailto:)#i', $compact)) {
            return $href;
        }
        // Relative: no scheme, i.e. no ":" before the first "/", "?" or "#".
        $head = strpbrk($compact, '/?#');
        $beforePath = $head === false ? $compact : substr($compact, 0, strlen($compact) - strlen($head));

        return str_contains($beforePath, ':') ? null : $href;
    }
}
