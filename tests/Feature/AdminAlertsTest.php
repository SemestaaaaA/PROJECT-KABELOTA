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
}
