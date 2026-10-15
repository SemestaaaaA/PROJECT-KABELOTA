<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hrd(): User
    {
        return User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
    }

    public function test_profile_email_defaults_to_the_account_email(): void
    {
        $user = User::factory()->create(['role' => 'talenta', 'email' => 'akun@contoh.id']);

        $this->actingAs($user)->get('/profil')->assertOk()
            ->assertSee('value="akun@contoh.id"', false)
            ->assertSee('boleh beda dengan email login');
    }
}
