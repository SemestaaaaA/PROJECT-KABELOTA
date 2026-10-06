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
}
