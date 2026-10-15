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

    public function test_guest_still_fills_name_and_email_on_contact(): void
    {
        $this->get('/kontak')->assertOk()->assertSee('id="c-name"', false)->assertSee('Ke mana pesan ini dikirim?');
        $this->post('/kontak', ['topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.'])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_logged_in_user_sends_contact_message_as_their_account(): void
    {
        $user = User::factory()->create(['role' => 'talenta', 'name' => 'Rahmat Uji', 'email' => 'rahmat@contoh.id']);

        $this->actingAs($user)->get('/kontak')->assertOk()
            ->assertDontSee('id="c-name"', false)
            ->assertSee('Dikirim sebagai')->assertSee('rahmat@contoh.id');

        $this->post('/kontak', [
            'name' => 'Nama Palsu', 'email' => 'palsu@contoh.id', 'topic' => 'Kendala akun', 'message' => 'Saya tidak bisa mengunggah CV.',
        ])->assertRedirect('/kontak');

        $message = ContactMessage::latest('id')->firstOrFail();
        $this->assertSame(['Rahmat Uji', 'rahmat@contoh.id', $user->id], [$message->name, $message->email, $message->user_id]);
    }

    public function test_company_contact_message_uses_company_name(): void
    {
        $hrd = $this->hrd();
        $this->actingAs($hrd)->post('/kontak', ['topic' => 'Kerja sama perusahaan', 'message' => 'Kami ingin kerja sama pilot.'])->assertRedirect('/kontak');

        $this->assertSame($hrd->company->name, ContactMessage::latest('id')->value('name'));
    }
}
