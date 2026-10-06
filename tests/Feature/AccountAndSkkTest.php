<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Talent;
use App\Models\User;
use App\Notifications\SkkExpiring;
use App\Notifications\SkkReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAndSkkTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Alumni with one SKK, a photo, a CV and an SKK scan, created through the real profile form. */
    private function alumni(array $skk = []): array
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'talenta', 'password' => 'rahasia123']);

        $this->actingAs($user)->post('/profil', [
            'type' => 'alumni', 'name' => 'Sari Uji, S.T.', 'phone' => '081200000001', 'email' => 'sari@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'graduation_year' => 2018, 'experience_since' => 2018,
            'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'skk' => [$skk + ['jabatan_kerja' => 'Ahli Teknik Jembatan', 'jenjang' => 7, 'registration_number' => 'SKK-001', 'expires_at' => now()->addYears(2)->toDateString()]],
            'photo' => UploadedFile::fake()->image('foto.jpg', 400, 400),
            'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'skk_scan' => UploadedFile::fake()->create('skk.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return [$user, Talent::where('user_id', $user->id)->firstOrFail()];
    }

    public function test_account_page_changes_password_and_email(): void
    {
        [$user] = $this->alumni();

        $this->actingAs($user)->get('/akun')->assertOk()->assertSee('Hapus akun')->assertSee('Sembunyikan profil');

        $this->post('/akun/sandi', ['current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->post('/akun/sandi', ['current_password' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasNoErrors();

        Notification::fake();
        $this->post('/akun/email', ['email' => 'sari.baru@contoh.id', 'current_password' => 'baru12345'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame('sari.baru@contoh.id', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_talent_can_delete_account_with_all_files(): void
    {
        [$user, $talent] = $this->alumni();
        $files = [$talent->cv_path, $talent->skk_scan_path];
        Storage::disk('public')->assertExists($talent->photo_path);

        $this->actingAs($user)->delete('/akun', ['current_password' => 'rahasia123', 'confirm' => 'hapus'])
            ->assertSessionHasErrorsIn('delete', 'confirm');

        $this->delete('/akun', ['current_password' => 'rahasia123', 'confirm' => 'HAPUS'])->assertSessionHasNoErrors()->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertModelMissing($talent);
        $this->assertSame(0, Certification::where('talent_id', $talent->id)->count());
        Storage::disk('public')->assertMissing($talent->photo_path);
        foreach ($files as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_company_account_deletion_removes_company_and_jobs(): void
    {
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        $hrd->update(['password' => 'rahasia123']);
        $company = $hrd->company;
        $this->assertTrue(JobPosting::where('company_id', $company->id)->exists());

        $this->actingAs($hrd)->delete('/akun', ['current_password' => 'rahasia123', 'confirm' => 'HAPUS'])->assertRedirect(route('home'));

        $this->assertModelMissing($company);
        $this->assertFalse(JobPosting::where('company_id', $company->id)->exists());
    }

    public function test_hidden_profile_leaves_search_and_blocks_offers(): void
    {
        [$user, $talent] = $this->alumni();

        $this->actingAs($user)->post('/akun/visibilitas', ['is_visible' => 0]);
        $this->assertFalse($talent->fresh()->is_visible);

        $this->assertFalse(Talent::search([])->whereKey($talent->id)->exists());
        $this->actingAs($user)->get(route('talents.show', $talent))->assertOk()->assertSee('Profil Anda sedang disembunyikan');

        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        $this->actingAs($hrd)->get(route('talents.show', $talent))->assertNotFound();
        $this->actingAs($hrd)->post(route('offers.store', $talent), [
            'company_name' => 'CV Uji', 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Ahli Jembatan', 'consent' => '1',
        ])->assertStatus(422);

        $this->actingAs($user)->post('/akun/visibilitas', ['is_visible' => 1]);
        $this->assertTrue(Talent::search([])->whereKey($talent->id)->exists());
    }

    public function test_admin_skk_verification_resets_when_scan_changes(): void
    {
        Notification::fake();
        [$user, $talent] = $this->alumni();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.file', ['skk', $talent->id]))->assertOk();
        $this->actingAs($admin)->get('/admin/talent')->assertOk()->assertSee('Perlu dicek')->assertSee('Verifikasi SKK');
        $this->actingAs($user)->get(route('admin.file', ['skk', $talent->id]))->assertForbidden();

        $talent->update(['skk_verified_at' => now()]);
        $user->notify(new SkkReviewed($talent));
        Notification::assertSentTo($user, SkkReviewed::class);

        $this->get('/talenta?terverifikasi=1&view=list')->assertOk()->assertSee('Sari Uji');
        $this->get(route('talents.show', $talent))->assertSee('SKK Terverifikasi');

        // Saving the profile with the same SKK keeps the badge; a new scan clears it.
        $payload = [
            'type' => 'alumni', 'name' => 'Sari Uji, S.T.', 'phone' => '081200000001', 'email' => 'sari@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'graduation_year' => 2018, 'experience_since' => 2018,
            'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'skk' => [['jabatan_kerja' => 'Ahli Teknik Jembatan', 'jenjang' => 7, 'registration_number' => 'SKK-001', 'expires_at' => $talent->certifications->first()->expires_at->toDateString()]],
        ];
        $this->actingAs($user)->post('/profil', $payload)->assertSessionHasNoErrors();
        $this->assertNotNull($talent->fresh()->skk_verified_at);

        $this->actingAs($user)->post('/profil', $payload + ['skk_scan' => UploadedFile::fake()->create('skk-baru.pdf', 100, 'application/pdf')]);
        $this->assertNull($talent->fresh()->skk_verified_at);
        $this->get('/talenta?terverifikasi=1&view=list')->assertDontSee('Sari Uji');
    }

    public function test_expired_jobs_close_and_company_can_close_early(): void
    {
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        $company = Company::where('status', 'terverifikasi')->whereKeyNot($hrd->company->id)->firstOrFail();
        $base = ['company_id' => $company->id, 'package' => 'reguler', 'location' => 'Palu', 'duration_months' => 3, 'description' => 'Uji', 'status' => 'aktif'];
        $expired = JobPosting::create($base + ['title' => 'Sudah lewat', 'closes_at' => today()->subDay()]);
        $live = JobPosting::create($base + ['title' => 'Masih tayang', 'closes_at' => today()->addWeek()]);

        $this->artisan('kabelota:close-expired')->assertSuccessful();
        $this->assertSame('ditutup', $expired->fresh()->status);
        $this->assertSame('aktif', $live->fresh()->status);

        $own = JobPosting::create(['company_id' => $hrd->company->id, 'title' => 'Tutup awal', 'closes_at' => today()->addWeek()] + $base);
        $this->actingAs($hrd)->post(route('company.jobs.close', $own))->assertRedirect();
        $this->assertSame('ditutup', $own->fresh()->status);
        $this->actingAs($hrd)->post(route('company.jobs.close', $live))->assertForbidden();
        $this->get(route('jobs.show', $own))->assertNotFound();
    }

    public function test_skk_expiry_reminder_is_sent_once(): void
    {
        Notification::fake();
        [$user, $talent] = $this->alumni(['expires_at' => today()->addDays(20)->toDateString()]);

        $this->artisan('kabelota:remind-skk')->assertSuccessful();
        $this->artisan('kabelota:remind-skk')->assertSuccessful();

        Notification::assertSentToTimes($user, SkkExpiring::class, 1);
    }

    public function test_user_can_download_own_data_and_consent_is_recorded(): void
    {
        $this->post('/daftar', ['role' => 'talenta', 'name' => 'Data Uji', 'email' => 'data@contoh.id', 'password' => 'rahasia123', 'consent' => '1']);
        $this->assertNotNull(User::where('email', 'data@contoh.id')->value('consented_at'));

        [$user] = $this->alumni();
        $response = $this->actingAs($user)->get('/akun/data')->assertOk()->assertDownload();
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame($user->email, $json['akun']['email']);
        $this->assertSame('081200000001', $json['profil_talenta']['phone']);
        $this->assertArrayNotHasKey('cv_path', $json['profil_talenta']);
        $this->assertNotEmpty($json['profil_talenta']['certifications']);
    }
}
