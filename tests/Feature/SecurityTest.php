<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_honeypot_blocks_bots_on_contact_and_register(): void
    {
        $contact = ['name' => 'Bot', 'email' => 'bot@spam.test', 'topic' => 'Pertanyaan umum', 'message' => 'Beli obat murah sekarang juga.'];
        $before = ContactMessage::count();

        $this->post('/kontak', $contact + ['kb_trap' => 'http://spam.test'])->assertSessionHasErrors('kb_trap');
        $this->assertSame($before, ContactMessage::count());

        $this->post('/daftar', ['role' => 'talenta', 'name' => 'Bot', 'email' => 'bot@spam.test', 'password' => 'rahasia123', 'consent' => '1', 'kb_trap' => 'x'])
            ->assertSessionHasErrorsIn('register', 'kb_trap');
        $this->assertGuest();
    }

    public function test_hidden_profile_documents_are_private_even_for_verified_companies(): void
    {
        Storage::fake('local');
        $talent = Talent::firstOrFail();
        $talent->update(['cv_path' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')->store('documents/cv', 'local')]);
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();

        $this->actingAs($hrd)->get(route('talents.document', [$talent, 'cv']))->assertOk();

        $talent->update(['is_visible' => false]);
        $this->actingAs($hrd)->get(route('talents.document', [$talent, 'cv']))->assertForbidden();
        $this->actingAs(User::where('role', 'admin')->first())->get(route('talents.document', [$talent, 'cv']))->assertOk();
    }

    public function test_content_security_policy_is_sent(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_back_link_never_points_to_another_site(): void
    {
        $talent = Talent::firstOrFail();

        $this->get(route('talents.show', $talent), ['referer' => 'https://evil.example/phish'])
            ->assertOk()
            ->assertDontSee('evil.example');
    }

    public function test_admin_role_cannot_be_self_assigned_at_registration(): void
    {
        $this->post('/daftar', ['role' => 'admin', 'name' => 'Nakal', 'email' => 'nakal@contoh.id', 'password' => 'rahasia123', 'consent' => '1'])
            ->assertSessionHasErrorsIn('register', 'role');
        $this->assertDatabaseMissing('users', ['email' => 'nakal@contoh.id']);
    }

    public function test_contact_message_is_stored_and_emailed_to_admin(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        config(['kabelota.ops_email' => 'ops@contoh.id']);

        $this->post('/kontak', ['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Apakah mahasiswa semester 5 boleh daftar?', 'kb_trap' => ''])
            ->assertRedirect(route('contact'));

        $this->assertDatabaseHas('contact_messages', ['email' => 'rina@contoh.id']);
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\ContactReceived::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@contoh.id');
    }
}
