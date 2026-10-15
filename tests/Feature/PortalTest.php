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

    public function test_nav_shows_the_page_for_the_visitors_role(): void
    {
        $this->get('/lowongan')->assertSee('>Untuk Perusahaan</a>', false)->assertDontSee('>Untuk Talenta</a>', false);

        $talent = User::factory()->create(['role' => 'talenta']);
        $this->actingAs($talent)->get('/lowongan')->assertSee('>Untuk Talenta</a>', false)->assertDontSee('>Untuk Perusahaan</a>', false)
            ->assertSee(url('/untuk-perusahaan'), false); // footer keeps both

        $this->actingAs($this->hrd())->get('/lowongan')->assertSee('>Untuk Perusahaan</a>', false)->assertDontSee('>Untuk Talenta</a>', false);
    }

    public function test_for_talents_page(): void
    {
        $this->get('/untuk-talenta')->assertOk()->assertSee('Profil Anda dicari')->assertSee('Daftar Gratis');
        $this->actingAs(User::factory()->create(['role' => 'talenta']))->get('/untuk-talenta')->assertOk()->assertSee('Lengkapi Profil')->assertDontSee('Daftar Gratis');
    }
}
