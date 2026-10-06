<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Talent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    // Same seeded database as the other feature tests; assertions below count changes, not totals.
    protected bool $seed = true;

    public function test_production_seeder_creates_only_the_admin(): void
    {
        config(['kabelota.demo_mode' => false, 'kabelota.admin_password' => 'sandi-admin-yang-panjang', 'kabelota.admin_email' => 'admin@kabelota.id']);

        $before = [User::count(), Talent::count(), Company::count()];

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // safe to run twice

        $this->assertSame([$before[0] + 1, $before[1], $before[2]], [User::count(), Talent::count(), Company::count()]);
        $this->assertSame('admin', User::where('email', 'admin@kabelota.id')->value('role'));
    }

    public function test_production_seeder_refuses_a_weak_admin_password(): void
    {
        config(['kabelota.demo_mode' => false, 'kabelota.admin_password' => 'pendek']);

        $this->expectException(\RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_seeder_fills_sample_data(): void
    {
        $this->assertSame(50, Talent::count());
        $this->artisan('kabelota:seed-if-empty')->expectsOutputToContain('dilewati')->assertSuccessful();
        $this->assertSame(50, Talent::count());
    }

    public function test_security_headers_and_noindex_outside_production(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_demo_login_is_closed_when_demo_mode_is_off(): void
    {
        config(['kabelota.demo_mode' => false]);

        $response = $this->post('/demo/masuk', ['role' => 'perusahaan']);
        $this->assertGuest();
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_admin_two_factor_is_required_only_outside_demo(): void
    {
        $panel = Filament::getPanel('admin');

        config(['kabelota.demo_mode' => true]);
        $this->assertFalse($panel->isMultiFactorAuthenticationRequired());

        config(['kabelota.demo_mode' => false]);
        $this->assertTrue($panel->isMultiFactorAuthenticationRequired());
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_robots_and_sitemap_in_production(): void
    {
        $this->app['env'] = 'production';
        $job = \App\Models\JobPosting::open()->firstOrFail();

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: ');
        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('jobs.show', $job), false)
            ->assertDontSee('/talenta/', false);

        // Talent profiles never get indexed; job pages carry JobPosting data for Google.
        $this->get(route('talents.show', \App\Models\Talent::first()))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('jobs.show', $job))->assertOk()->assertSee('"@type":"JobPosting"', false)->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_analytics_script_only_when_configured(): void
    {
        $this->get('/')->assertDontSee('data-website-id', false);

        config(['kabelota.analytics' => ['host' => 'https://cloud.umami.is', 'website_id' => 'abc-123']]);
        $this->get('/')->assertSee('https://cloud.umami.is/script.js', false)->assertSee('data-website-id="abc-123"', false);
    }

    public function test_preflight_blocks_launch_with_demo_settings(): void
    {
        $this->artisan('kabelota:preflight')
            ->expectsOutputToContain('Mode demo menyala')
            ->expectsOutputToContain('Rekening pembayaran masih placeholder')
            ->assertFailed();
    }
}
