<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Models\AdminActivity;
use App\Models\SiteText as SiteTextRow;
use App\Models\SiteTextRevision;
use App\Models\User;
use App\Support\SiteText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** Mode CMS: admin edits marketing copy in place; overrides are sanitized, versioned and logged. */
class SiteTextTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const KEY = 'home.hero.title';

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    /** Every literal `<x-cms key="...">` in resources/views. */
    public static function keysInViews(): array
    {
        $keys = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                preg_match_all('/<x-cms\s[^>]*\bkey="([^"]+)"/', file_get_contents($file->getPathname()), $m);
                array_push($keys, ...$m[1]);
            }
        }
        sort($keys);

        return $keys;
    }

    private function saveText(string $key, string $html, int|string|null $expected = 0): TestResponse
    {
        return $this->putJson('/cms/'.$key, ['html' => $html, 'expected' => $expected]);
    }

    // ---- Access ----

    public function test_guests_and_non_admins_get_403_on_every_cms_route(): void
    {
        $calls = [
            ['post', '/cms/mode'],
            ['put', '/cms/'.self::KEY],
            ['get', '/cms/'.self::KEY.'/riwayat'],
            ['post', '/cms/'.self::KEY.'/pulihkan'],
        ];

        foreach ([null, 'talenta', 'perusahaan'] as $role) {
            if ($role) {
                $this->actingAs(User::factory()->create(['role' => $role]));
            }
            foreach ($calls as [$method, $url]) {
                $this->json($method, $url, ['html' => 'Coba'])->assertForbidden();
                // A plain browser request (no JSON) is refused too, never redirected to the login page.
                $this->call(strtoupper($method), $url, ['html' => 'Coba'])->assertForbidden();
            }
        }

        $this->assertSame(0, SiteTextRow::count());
    }

    public function test_admin_toggles_edit_mode_in_the_session(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/cms/mode')->assertOk()->assertJson(['edit' => true]);
        $this->assertTrue(session('cms_edit'));
        $this->from('/tentang')->post('/cms/mode')->assertRedirect('/tentang');
        $this->assertFalse(session('cms_edit'));
    }

    public function test_unknown_key_is_rejected_with_422(): void
    {
        $this->actingAs($this->admin());

        $this->saveText('home.tidak.ada', 'Teks')->assertStatus(422);
        $this->getJson('/cms/home.tidak.ada/riwayat')->assertStatus(422);
        $this->postJson('/cms/home.tidak.ada/pulihkan')->assertStatus(422);
        $this->assertSame(0, SiteTextRow::count());
    }

    // ---- Sanitizer ----

    public function test_sanitizer_keeps_only_simple_formatting_and_safe_links(): void
    {
        $cases = [
            'Halo <script>alert(1)</script>dunia' => 'Halo dunia',
            '<style>body{display:none}</style>Teks' => 'Teks',
            '<a href="javascript:alert(1)">klik</a>' => 'klik',
            '<a href=" JaVa&#x09;Script:alert(1)">klik</a>' => 'klik',
            '<a href="data:text/html,x">klik</a>' => 'klik',
            '<b onclick="alert(1)" class="x" style="color:red">tebal</b>' => '<b>tebal</b>',
            '<a href="https://kabelota.id" target="_blank" onclick="x()">situs</a>' => '<a href="https://kabelota.id" rel="noopener noreferrer">situs</a>',
            '<a href="mailto:admin@kabelota.id">surel</a>' => '<a href="mailto:admin@kabelota.id" rel="noopener noreferrer">surel</a>',
            '<a href="/lowongan?lokasi=Palu&amp;x=1">lowongan</a>' => '<a href="/lowongan?lokasi=Palu&amp;x=1" rel="noopener noreferrer">lowongan</a>',
            '<div><span><font color="red"><i>miring</i></font></span></div>' => '<i>miring</i>',
            '<img src=x onerror="alert(1)">gambar' => 'gambar',
            '<p>satu</p><p>dua</p>' => 'satu<br>dua',
            'baris<br>baru' => 'baris<br>baru',
            '<a href="https://a.id"><a href="https://b.id">dalam</a></a>' => '<a href="https://a.id" rel="noopener noreferrer"></a><a href="https://b.id" rel="noopener noreferrer">dalam</a>',
            '5 < 6 & "kutip"' => '5 &lt; 6 &amp; &quot;kutip&quot;',
            'Ahli<!-- komentar --> teknik' => 'Ahli teknik',
            '<a href="/\\evil.com">miring balik</a>' => 'miring balik',
            '<a href="https://kabelota.id\\@evil.com">campur</a>' => 'campur',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, SiteText::sanitize($input), 'Input: '.$input);
        }
    }

    public function test_save_rejects_empty_and_too_long_text(): void
    {
        $this->actingAs($this->admin());

        $this->saveText(self::KEY, '<b> </b><script>x</script>')->assertStatus(422)->assertJsonValidationErrors('html');
        $this->saveText(self::KEY, str_repeat('a', 2001))->assertStatus(422)->assertJsonValidationErrors('html');
        $this->saveText(self::KEY, '<b>'.str_repeat('a', 2000).'</b>')->assertOk();
    }

    // ---- Reading ----

    public function test_empty_database_returns_the_default(): void
    {
        $this->assertSame([], SiteText::all());
        $this->assertNull(SiteText::get(self::KEY));
    }

    public function test_all_runs_at_most_one_query_per_request(): void
    {
        DB::enableQueryLog();
        SiteText::all();
        SiteText::all();
        SiteText::get(self::KEY);
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'site_texts'));
        $this->assertCount(1, $queries);
    }

    // ---- Saving ----

    public function test_save_creates_a_revision_and_an_activity(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $first = $this->saveText(self::KEY, 'Teks <b>baru</b><script>x</script>')->assertOk()
            ->assertJsonPath('html', 'Teks <b>baru</b>');
        $this->assertSame('Teks <b>baru</b>', SiteText::get(self::KEY));

        $row = SiteTextRow::where('key', self::KEY)->firstOrFail();
        $this->assertSame($admin->id, $row->updated_by);
        $this->assertSame(1, $row->version);
        $this->assertSame(1, $first->json('version'));

        $revision = SiteTextRevision::where('key', self::KEY)->sole();
        $this->assertNull($revision->html, 'The first revision remembers that the default was showing.');
        $this->assertSame($admin->id, $revision->user_id);

        $activity = AdminActivity::where('action', 'text_edited')->sole();
        $this->assertSame($row->getMorphClass(), $activity->subject_type);
        $this->assertSame($row->id, $activity->subject_id);
        $this->assertStringContainsString(self::KEY, $activity->description);

        // A second save keeps the previous override as a revision.
        $this->saveText(self::KEY, 'Teks kedua', $first->json('version'))->assertOk()->assertJsonPath('version', 2);
        $this->assertSame('Teks <b>baru</b>', SiteTextRevision::where('key', self::KEY)->latest('id')->first()->html);
        $this->assertSame('Teks kedua', SiteText::get(self::KEY));
    }

    public function test_saving_the_same_text_writes_nothing(): void
    {
        $this->actingAs($this->admin());
        $version = $this->saveText(self::KEY, 'Sama')->json('version');
        $this->saveText(self::KEY, 'Sama', $version)->assertOk()->assertJsonPath('version', $version);

        $this->assertSame(1, SiteTextRevision::count());
        $this->assertSame(1, AdminActivity::where('action', 'text_edited')->count());
    }

    public function test_stale_or_missing_expected_returns_409_and_keeps_the_text(): void
    {
        $this->actingAs($this->admin());
        $this->saveText(self::KEY, 'Versi admin A')->assertOk();

        $this->saveText(self::KEY, 'Versi admin B', 5)->assertStatus(409)
            ->assertJsonPath('message', 'Teks sudah diubah admin lain, muat ulang.');
        // Version 0 (or empty) means "I saw the default", which is only true while no row exists.
        $this->saveText(self::KEY, 'Versi admin B', 0)->assertStatus(409);
        $this->saveText(self::KEY, 'Versi admin B', '')->assertStatus(409);
        $this->saveText(self::KEY, 'Versi admin B', 'abc')->assertStatus(422);

        $this->assertSame('Versi admin A', SiteText::get(self::KEY));
        $this->assertSame(1, SiteTextRevision::count());
    }

    public function test_two_saves_in_the_same_second_with_an_old_version_conflict(): void
    {
        $this->freezeTime();
        $this->actingAs($this->admin());

        $this->saveText(self::KEY, 'Admin A menyimpan')->assertOk()->assertJsonPath('version', 1);
        $this->saveText(self::KEY, 'Admin A lagi', 1)->assertOk()->assertJsonPath('version', 2);
        // Admin B opened the page at version 1, in the same second: the timestamp is unchanged, the version is not.
        $this->saveText(self::KEY, 'Admin B menimpa', 1)->assertStatus(409);

        $this->assertSame('Admin A lagi', SiteText::get(self::KEY));
    }

    public function test_activity_names_the_editor_even_without_a_login_session(): void
    {
        $admin = $this->admin();
        $this->assertNull(auth()->id());

        SiteText::save(self::KEY, 'Dari perintah konsol', $admin, 0);
        SiteText::restore(self::KEY, null, $admin);

        $this->assertSame([$admin->id, $admin->id], AdminActivity::orderBy('id')->pluck('user_id')->all());
    }

    // ---- History and restore ----

    public function test_history_lists_the_last_revisions(): void
    {
        $this->actingAs($this->admin());
        $version = 0;
        foreach (range(1, 22) as $i) {
            $version = $this->saveText(self::KEY, 'Versi '.$i, $version)->assertOk()->json('version');
        }

        $list = $this->getJson('/cms/'.self::KEY.'/riwayat')->assertOk()->json('revisions');
        $this->assertCount(20, $list);
        $this->assertSame('Versi 21', $list[0]['text']);
        $this->assertSame($this->admin()->name, $list[0]['user']);
        $this->assertArrayHasKey('created_at', $list[0]);
        $this->assertArrayHasKey('id', $list[0]);
    }

    public function test_restore_a_revision(): void
    {
        $this->actingAs($this->admin());
        $version = $this->saveText(self::KEY, 'Versi pertama')->json('version');
        $this->saveText(self::KEY, 'Versi kedua', $version)->assertOk();

        $revision = SiteTextRevision::where('key', self::KEY)->where('html', 'Versi pertama')->sole();
        $this->postJson('/cms/'.self::KEY.'/pulihkan', ['revision' => $revision->id])->assertOk()
            ->assertJsonPath('html', 'Versi pertama');

        $this->assertSame('Versi pertama', SiteText::get(self::KEY));
        $this->assertSame('Versi kedua', SiteTextRevision::where('key', self::KEY)->latest('id')->first()->html);
        $this->assertSame(1, AdminActivity::where('action', 'text_restored')->count());

        // A revision of another key cannot be used.
        $this->saveText('home.hero.sub', 'Lain')->assertOk();
        $other = SiteTextRevision::where('key', 'home.hero.sub')->sole();
        $this->postJson('/cms/'.self::KEY.'/pulihkan', ['revision' => $other->id])->assertNotFound();
    }

    public function test_restore_to_the_default_keeps_the_row_with_null_html(): void
    {
        $this->actingAs($this->admin());
        $this->saveText(self::KEY, 'Teks sementara')->assertOk();
        $this->assertNotNull(SiteText::get(self::KEY));

        $this->postJson('/cms/'.self::KEY.'/pulihkan')->assertOk()->assertJsonPath('html', null);

        $row = SiteTextRow::where('key', self::KEY)->sole();
        $this->assertNull($row->html);
        $this->assertNull(SiteText::get(self::KEY));
        $this->assertArrayNotHasKey(self::KEY, array_filter(array_map(fn ($t) => $t['html'], SiteText::all())));
        $this->assertSame('Teks sementara', SiteTextRevision::where('key', self::KEY)->latest('id')->first()->html);

        $activity = AdminActivity::where('action', 'text_restored')->sole();
        $this->assertSame($row->id, $activity->subject_id);
        $this->assertStringContainsString('teks asli', $activity->description);

        // After a restore to default the row keeps counting: the editor sends the row's version, not 0.
        $this->assertSame(2, $row->version);
        $this->saveText(self::KEY, 'Lagi', 0)->assertStatus(409);
        $this->saveText(self::KEY, 'Lagi', $row->version)->assertOk()->assertJsonPath('version', 3);
    }

    public function test_new_actions_show_in_the_activity_log(): void
    {
        $this->actingAs($this->admin());
        $this->saveText(self::KEY, 'Teks untuk riwayat')->assertOk();
        $this->postJson('/cms/'.self::KEY.'/pulihkan')->assertOk();

        $this->assertSame('Teks situs diubah', AdminActivity::ACTIONS['text_edited']);
        $this->assertSame('Teks situs dikembalikan', AdminActivity::ACTIONS['text_restored']);

        $edited = AdminActivity::where('action', 'text_edited')->sole();
        $restored = AdminActivity::where('action', 'text_restored')->sole();
        Livewire::test(ListAdminActivities::class)
            ->assertCanSeeTableRecords([$edited, $restored])
            ->assertSee('Teks situs diubah')
            ->filterTable('action', 'text_restored')
            ->assertCanSeeTableRecords([$restored])
            ->assertCanNotSeeTableRecords([$edited]);
        $this->get('/admin/admin-activities')->assertOk();
    }

    // ---- Pages ----

    /** Each converted page, with a default sentence that must render unchanged when no override exists. */
    private const PAGES = [
        '/' => 'Tenaga ahli daerah, membangun daerahnya.',
        '/tentang' => 'Kenapa HMTS memulai ini',
        '/untuk-talenta' => 'Profil Anda dicari perusahaan konstruksi Sulawesi Tengah.',
        '/untuk-perusahaan' => 'Tenaga ahli lokal untuk tender Anda.',
        '/kontak' => 'Pertanyaan, kerja sama, atau kendala akun. Admin membalas di hari kerja.',
    ];

    public function test_pages_render_the_defaults_for_guests_without_editor_markup(): void
    {
        foreach (self::PAGES as $url => $sentence) {
            $this->get($url)->assertOk()->assertSee($sentence)
                ->assertDontSee('data-cms-key', false)
                ->assertDontSee('cms-editable', false)
                ->assertDontSee('data-cms-toggle', false)
                ->assertDontSee('/build/assets/cms-', false);
        }

        // The story quote keeps its inline markup from the Blade default.
        $this->get('/')->assertSee('berarti <em>kebaikan untuk bersama.</em>', false);
    }

    public function test_non_admins_never_get_the_toggle_or_editor(): void
    {
        foreach (['talenta', 'perusahaan'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->withSession(['cms_edit' => true]);
            $this->get('/tentang')->assertOk()
                ->assertDontSee('data-cms-toggle', false)
                ->assertDontSee('data-cms-key', false)
                ->assertDontSee('/build/assets/cms-', false);
        }
    }

    public function test_admin_sees_the_toggle_and_in_edit_mode_the_editor(): void
    {
        $this->actingAs($this->admin());

        $this->get('/tentang')->assertOk()
            ->assertSee('data-cms-toggle', false)
            ->assertSee('Mode edit')
            ->assertDontSee('data-cms-key', false)
            ->assertDontSee('/build/assets/cms-', false);

        $this->withSession(['cms_edit' => true]);
        foreach (array_keys(self::PAGES) as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-cms-toggle', false)
                ->assertSee('Selesai mengedit')
                ->assertSee('data-cms-key=', false)
                ->assertSee('cms-editable', false)
                ->assertSee('/build/assets/cms-', false);
        }

        // The default travels escaped in an attribute, and the version is 0 until the first save.
        $this->get('/')->assertSee('data-cms-key="home.story.quote"', false)
            ->assertSee('data-cms-default="&quot;Kabelota&quot; dalam bahasa Kaili berarti &lt;em&gt;kebaikan untuk bersama.&lt;/em&gt;"', false)
            ->assertSee('data-cms-version="0"', false);
    }

    public function test_an_override_replaces_the_default_on_the_page(): void
    {
        $admin = $this->admin();
        $row = SiteText::save('about.hero.title', 'Judul <b>baru</b> dari admin', $admin, 0);

        $this->get('/tentang')->assertOk()
            ->assertSee('Judul <b>baru</b> dari admin', false)
            ->assertDontSee('Kebaikan untuk bersama.</h1>', false);

        $this->actingAs($admin)->withSession(['cms_edit' => true])->get('/tentang')
            ->assertSee('data-cms-version="'.$row->version.'"', false);

        // Restoring the default brings the Blade text back.
        SiteText::restore('about.hero.title', null, $admin);
        $this->get('/tentang')->assertSee('Kebaikan untuk bersama.')->assertDontSee('Judul <b>baru</b>', false);
    }

    // ---- Keys ----

    public function test_keys_match_the_views(): void
    {
        $inViews = self::keysInViews();
        $this->assertSame(count($inViews), count(array_unique($inViews)), 'A key is used twice in the views.');

        $registered = SiteText::KEYS;
        sort($registered);
        $this->assertSame($registered, $inViews);
    }
}
