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
        'home.hero.title',
        'home.hero.sub',
    ];

    public const MAX_LENGTH = 2000;

    private const CACHE_KEY = 'site_texts';

    /** Tags kept as they are. `a` and `br` are handled on their own. */
    private const INLINE = ['b', 'strong', 'i', 'em'];

    /** Dropped together with everything inside them. Any other tag is unwrapped (its text stays). */
    private const DROP = ['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'textarea', 'select', 'button'];

    /** Block tags that become a line break when unwrapped (contenteditable wraps new lines in them). */
    private const BLOCK = ['div', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'tr'];

    /** Overrides keyed by key: ['html' => ?string, 'updated' => string]. One cache read per request. */
    public static function all(): array
    {
        return Cache::memo()->rememberForever(self::CACHE_KEY, fn () => SiteTextRow::query()
            ->get(['key', 'html', 'updated_at'])
            ->mapWithKeys(fn (SiteTextRow $row) => [$row->key => ['html' => $row->html, 'updated' => self::stamp($row)]])
            ->all());
    }

    /** The stored (already sanitized) override, or null to show the default. */
    public static function get(string $key): ?string
    {
        return self::all()[$key]['html'] ?? null;
    }

    /** The version the editor saw, sent back as `expected` on save. Empty when no row exists yet. */
    public static function updated(string $key): string
    {
        return self::all()[$key]['updated'] ?? '';
    }

    public static function stamp(?SiteTextRow $row): string
    {
        return $row?->updated_at?->format('Y-m-d H:i:s') ?? '';
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

    /** Save an override. $expected is the stamp the editor saw ('' = the default, with no row yet). */
    public static function save(string $key, string $html, User $user, ?string $expected): SiteTextRow
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

        return self::fresh(DB::transaction(function () use ($key, $clean, $user, $expected) {
            $row = SiteTextRow::where('key', $key)->lockForUpdate()->first();
            if (self::stamp($row) !== (string) $expected) {
                throw new ConflictHttpException('Teks sudah diubah admin lain, muat ulang.');
            }
            if ($row?->html === $clean) {
                return $row;
            }

            return self::write($key, $row, $clean, $user, 'text_edited', 'Teks "'.$key.'" diubah: '.self::snippet($clean, 160));
        }));
    }

    /** Restore a revision of this key, or the default when $revisionId is null. Returns null if nothing changed. */
    public static function restore(string $key, ?int $revisionId, User $user): ?SiteTextRow
    {
        self::assertKey($key);

        return self::fresh(DB::transaction(function () use ($key, $revisionId, $user) {
            $html = $revisionId === null ? null : SiteTextRevision::where('key', $key)->findOrFail($revisionId)->html;
            $row = SiteTextRow::where('key', $key)->lockForUpdate()->first();
            if (($row?->html) === $html) {
                return $row;
            }

            return self::write($key, $row, $html, $user, 'text_restored', $html === null
                ? 'Teks "'.$key.'" dikembalikan ke teks asli.'
                : 'Teks "'.$key.'" dipulihkan ke versi sebelumnya: '.self::snippet($html, 160));
        }));
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
        $row->fill(['html' => $html, 'updated_by' => $user->id])->save();
        AdminActivity::record($action, $row, $description);

        return $row;
    }

    /** Drop the cached overrides once a write is committed, so every page reads the new text. */
    private static function fresh(?SiteTextRow $row): ?SiteTextRow
    {
        Cache::memo()->forget(self::CACHE_KEY);

        return $row;
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
        if ($href === '' || str_starts_with($compact, '//') || str_starts_with($compact, '\\')) {
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
