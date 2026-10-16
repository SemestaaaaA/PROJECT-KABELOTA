<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function latestAdminTitle(): string
    {
        return $this->admin()->notifications()->latest()->firstOrFail()->data['title'];
    }

    public function test_alert_reaches_every_admin_and_nobody_else(): void
    {
        $second = User::factory()->create(['role' => 'admin']);
        $talent = User::factory()->create(['role' => 'talenta']);

        AdminAlerts::send('contact_message', 'Pesan kontak baru', 'Rina: Kendala akun', '/admin/contact-messages');

        $this->assertSame(1, $this->admin()->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(0, $talent->notifications()->count());
        $this->assertSame('Pesan kontak baru', $this->admin()->notifications()->first()->data['title']);
    }

    public function test_alert_without_any_admin_is_a_no_op(): void
    {
        User::where('role', 'admin')->delete();

        AdminAlerts::send('job_failed', 'Job gagal: Uji', 'Pesan galat.', '/admin');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_contact_message_raises_an_alert(): void
    {
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $this->assertStringContainsString('Pesan kontak', $this->latestAdminTitle());
    }

    public function test_new_job_posting_with_payment_proof_raises_an_alert(): void
    {
        Storage::fake('local');
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();

        $this->actingAs($hrd)->post('/lowongan/pasang', [
            'title' => 'Site Engineer Uji Coba', 'location' => 'Palu', 'duration_months' => 6,
            'description' => 'Mengawasi pekerjaan jalan dan menyusun laporan harian untuk paket uji coba.',
            'package' => 'tenaga_ahli', 'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect();

        $this->assertStringContainsString('Bukti bayar', $this->latestAdminTitle());
        $this->assertStringContainsString('Site Engineer Uji Coba', $this->admin()->notifications()->latest()->first()->data['body']);
    }

    public function test_company_document_upload_raises_an_alert(): void
    {
        Storage::fake('local');
        $this->post('/daftar', ['role' => 'perusahaan', 'name' => 'CV Uji Bangun', 'email' => 'hrd@ujibangun.id', 'password' => 'rahasia123', 'consent' => '1']);
        $user = User::where('email', 'hrd@ujibangun.id')->firstOrFail();
        $user->markEmailAsVerified();
        $before = $this->admin()->notifications()->count();

        $this->actingAs($user)->post('/perusahaan/profil', [
            'name' => 'CV Uji Bangun', 'type' => 'kontraktor', 'city' => 'Palu', 'nib' => '1234567890123',
            'contact_name' => 'Rina', 'contact_phone' => '0812', 'legal_doc' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('company.profile'));

        $this->assertSame($before + 1, $this->admin()->notifications()->count());
        $this->assertStringContainsString('verifikasi', $this->latestAdminTitle());
    }

    public function test_verified_company_saving_profile_raises_no_alert(): void
    {
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        $company = $hrd->company;
        $this->assertSame('terverifikasi', $company->status);

        $this->actingAs($hrd)->post('/perusahaan/profil', [
            'name' => $company->name, 'type' => $company->type, 'city' => $company->city, 'nib' => $company->nib ?: '1234567890123',
            'contact_name' => 'Rina', 'contact_phone' => '0812',
        ] + ($company->legal_doc_path ? [] : ['legal_doc' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf')]));

        $this->assertSame(0, $this->admin()->notifications()->count());
    }

    public function test_changed_skk_raises_an_alert(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'talenta']);

        $this->actingAs($user)->post('/profil', [
            'type' => 'alumni', 'name' => 'Sari Uji, S.T.', 'phone' => '081200000001', 'email' => 'sari@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'graduation_year' => 2018, 'experience_since' => 2018,
            'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'skk' => [['jabatan_kerja' => 'Ahli Teknik Jembatan', 'jenjang' => 7, 'registration_number' => 'SKK-001', 'expires_at' => now()->addYears(2)->toDateString()]],
            'skk_scan' => UploadedFile::fake()->create('skk.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertStringContainsString('SKK', $this->latestAdminTitle());
        $this->assertStringContainsString('Sari Uji', $this->admin()->notifications()->latest()->first()->data['body']);
    }

    public function test_contact_messages_track_read_state(): void
    {
        $message = \App\Models\ContactMessage::create(['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);
        $this->assertSame(1, \App\Models\ContactMessage::unread()->count());

        $message->markAsRead();

        $this->assertNotNull($message->fresh()->read_at);
        $this->assertSame(0, \App\Models\ContactMessage::unread()->count());
        $this->assertNull(\App\Filament\Resources\ContactMessages\ContactMessageResource::getNavigationBadge());
    }

    public function test_opening_a_contact_message_marks_it_read(): void
    {
        $first = \App\Models\ContactMessage::create(['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);
        $second = \App\Models\ContactMessage::create(['name' => 'Budi', 'email' => 'budi@contoh.id', 'topic' => 'Kendala akun', 'message' => 'Tidak bisa masuk ke akun saya.']);
        $this->assertSame('2', \App\Filament\Resources\ContactMessages\ContactMessageResource::getNavigationBadge());

        $this->actingAs($this->admin());
        $list = \App\Filament\Resources\ContactMessages\Pages\ListContactMessages::class;
        \Livewire\Livewire::test($list)
            ->assertSee('Baru')
            ->mountTableAction('view', $first)
            ->assertSee('Halo, mau tanya soal pilot.');
        \Livewire\Livewire::test($list)->callTableAction('baca', $second);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->get('/admin/contact-messages')->assertOk();
    }

    public function test_alert_text_from_user_input_cannot_inject_links(): void
    {
        $this->post('/kontak', ['name' => '<a href="https://evil.example/x">klik</a>', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo <a href="https://evil.example/y">di sini</a> ya.'])
            ->assertSessionHasNoErrors();

        $data = $this->admin()->notifications()->latest()->firstOrFail()->data;
        $this->assertStringNotContainsString('<a', $data['title'].$data['body']);

        $this->actingAs($this->admin());
        \Livewire\Livewire::test(\App\Filament\Widgets\AdminNotificationsWidget::class)
            ->assertDontSee('href="https://evil.example', false)
            ->assertSee('Pesan kontak: Pertanyaan umum');
    }

    public function test_rejected_company_resubmitting_raises_an_alert(): void
    {
        Storage::fake('local');
        $this->post('/daftar', ['role' => 'perusahaan', 'name' => 'CV Uji Bangun', 'email' => 'hrd@ujibangun.id', 'password' => 'rahasia123', 'consent' => '1']);
        $user = User::where('email', 'hrd@ujibangun.id')->firstOrFail();
        $user->markEmailAsVerified();
        $user->company->update(['status' => 'ditolak', 'rejection_reason' => 'NIB tidak terbaca', 'legal_doc_path' => 'legal-docs/lama.pdf']);
        $before = $this->admin()->notifications()->count();

        // No new file: only the status moves from "ditolak" back to "menunggu".
        $this->actingAs($user)->post('/perusahaan/profil', [
            'name' => 'CV Uji Bangun', 'type' => 'kontraktor', 'city' => 'Palu', 'nib' => '1234567890123',
            'contact_name' => 'Rina', 'contact_phone' => '0812',
        ])->assertSessionHasNoErrors();

        $this->assertSame('menunggu', $user->company->fresh()->status);
        $this->assertSame($before + 1, $this->admin()->notifications()->count());
        $this->assertStringContainsString('verifikasi', $this->latestAdminTitle());
    }

    public function test_talent_without_certifications_raises_no_skk_alert(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'talenta']);

        $this->actingAs($user)->post('/profil', [
            'type' => 'mahasiswa', 'semester' => 5, 'hmts_member' => '0', 'hmts_status' => 'pengurus',
            'name' => 'Rahmat Uji', 'phone' => '081200000000', 'email' => 'rahmat@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'skk_scan' => UploadedFile::fake()->create('skk.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->admin()->notifications()->count());
    }
}
