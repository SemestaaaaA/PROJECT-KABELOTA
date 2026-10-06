<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KabelotaDemoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hrd(): User
    {
        return User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
    }

    private function talentUser(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'talenta'] + $attrs);
    }

    private function profilePayload(array $extra = []): array
    {
        return $extra + [
            'name' => 'Rahmat Uji', 'phone' => '081200000000', 'email' => 'rahmat@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'projects' => [['name' => 'Asisten Lab Beton', 'position' => 'Asisten', 'location' => 'Palu', 'year_start' => 2025]],
        ];
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('membangun daerahnya', false);
        $this->get('/talenta')->assertOk()->assertSee('talenta cocok');
        $this->get('/talenta?view=list')->assertOk()->assertSee('<table', false);
        $this->get('/lowongan')->assertOk();
        $this->get('/tentang')->assertOk()->assertSee('Kenapa HMTS');
        $this->get('/kontak')->assertOk()->assertSee('hmts.tadulako');
        $this->get('/untuk-perusahaan')->assertOk()->assertSee('Rp200.000');
    }

    public function test_search_filters_by_jabatan_and_jenjang(): void
    {
        $response = $this->get('/talenta?view=list&jabatan=Ahli+Teknik+Jalan&jenjang[]=9');
        $expected = Talent::search(['jabatan' => 'Ahli Teknik Jalan', 'jenjang' => [9]])->get();
        $other = Talent::whereDoesntHave('certifications', fn ($q) => $q->where('jabatan_kerja', 'Ahli Teknik Jalan'))->first();

        $response->assertOk();
        $expected->each(fn ($t) => $response->assertSee(e($t->name), false));
        $response->assertDontSee(e($other->name), false);
    }

    public function test_register_login_and_logout(): void
    {
        $this->post('/daftar', ['role' => 'talenta', 'name' => 'Baru', 'email' => 'baru@contoh.id', 'password' => 'rahasia123', 'consent' => '1'])
            ->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'baru@contoh.id')->firstOrFail();
        $this->assertSame('talenta', $user->role);
        $this->assertAuthenticatedAs($user);

        // Unverified email cannot open the profile builder yet.
        $this->get('/profil')->assertRedirect(route('verification.notice'));

        $this->post('/keluar');
        $this->assertGuest();

        $this->post('/masuk', ['email' => 'baru@contoh.id', 'password' => 'salah'])->assertSessionHasErrorsIn('login', 'email');
        $this->post('/masuk', ['email' => 'baru@contoh.id', 'password' => 'rahasia123']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_company_registration_needs_admin_verification(): void
    {
        Storage::fake('local');
        $this->post('/daftar', ['role' => 'perusahaan', 'name' => 'CV Uji Bangun', 'email' => 'hrd@ujibangun.id', 'password' => 'rahasia123', 'consent' => '1']);
        $user = User::where('email', 'hrd@ujibangun.id')->firstOrFail();
        $user->markEmailAsVerified();
        $company = $user->company;
        $this->assertSame('menunggu', $company->status);

        // Pending company cannot post jobs or open documents.
        $this->actingAs($user)->get('/lowongan/pasang')->assertRedirect(route('company.profile'));

        $this->actingAs($user)->post('/perusahaan/profil', [
            'name' => 'CV Uji Bangun', 'type' => 'kontraktor', 'city' => 'Palu', 'nib' => '1234567890123',
            'contact_name' => 'Rina', 'contact_phone' => '0812', 'legal_doc' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('company.profile'));
        Storage::disk('local')->assertExists($company->fresh()->legal_doc_path);

        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->get(route('admin.file', ['legalitas', $company->id]))->assertOk();
        $this->actingAs($user)->get(route('admin.file', ['legalitas', $company->id]))->assertForbidden();

        $company->update(['status' => 'terverifikasi']);
        $this->actingAs($user->fresh())->get('/lowongan/pasang')->assertOk();
    }

    public function test_admin_panel_is_admin_only(): void
    {
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin')->assertOk();
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin/companies')->assertOk()->assertSee('CV Lembah Palu Konsultan');
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin/job-postings')->assertOk();
        $this->actingAs($this->hrd())->get('/admin')->assertForbidden();
    }

    public function test_profile_never_exposes_contact_details(): void
    {
        $talent = Talent::first();

        $this->get(route('talents.show', $talent))->assertOk()->assertDontSee($talent->phone)->assertDontSee($talent->email);
    }

    public function test_verified_company_sends_offer(): void
    {
        $talent = Talent::where('availability', Availability::Tersedia)->first();

        $this->post(route('offers.store', $talent), [])->assertRedirect(route('login'));

        $this->actingAs($this->hrd());
        $this->post(route('offers.store', $talent), ['company_name' => 'CV Uji'])
            ->assertSessionHasErrorsIn('offer', ['contact_name', 'contact_email', 'position', 'message']);

        $this->post(route('offers.store', $talent), [
            'company_name' => 'CV Lembah Palu Konsultan', 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id',
            'position' => 'Site Engineer', 'message' => 'Kami membutuhkan site engineer untuk paket jalan.',
        ])->assertRedirect(route('talents.show', $talent))->assertSessionHas('offer_sent');

        $this->assertDatabaseHas('recruitment_offers', ['talent_id' => $talent->id, 'company_id' => $this->hrd()->company->id, 'status' => 'menunggu']);
    }

    public function test_unavailable_talent_cannot_receive_offers(): void
    {
        $talent = Talent::where('availability', Availability::TidakTersedia)->firstOrFail();

        $this->actingAs($this->hrd())->post(route('offers.store', $talent), [
            'company_name' => 'CV Uji', 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id',
            'position' => 'Site Engineer', 'message' => 'Kami membutuhkan site engineer untuk paket jalan.',
        ])->assertStatus(422);
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'x'])->assertSessionHasErrors(['email', 'message']);
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.'])
            ->assertRedirect('/kontak');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_talent_builds_profile_then_graduates(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = $this->talentUser();
        $this->get('/profil')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/profil')->assertOk()->assertSee('Buat profil');

        $this->post('/profil', $this->profilePayload([
            'type' => 'mahasiswa', 'semester' => 7, 'hmts_member' => '1', 'hmts_status' => 'pengurus', 'hmts_position' => 'Sekretaris',
            'skills' => ['AutoCAD', 'HEC-RAS'],
            'cv' => UploadedFile::fake()->create('cv.pdf', 2500, 'application/pdf'),
            'photo' => UploadedFile::fake()->image('foto.jpg', 1200, 900),
        ]))->assertRedirect();

        $talent = $user->fresh()->talent;
        $this->assertSame('mahasiswa', $talent->type);
        $this->assertSame('pengurus', $talent->hmts_status);
        Storage::disk('local')->assertExists($talent->cv_path);
        Storage::disk('public')->assertExists($talent->photo_path);
        $this->assertStringEndsWith('.webp', $talent->photo_path);

        $this->get(route('talents.show', $talent))
            ->assertSee('Intern for Hire')->assertSee('HMTS · Pengurus')->assertSee('Saya Sudah Lulus')->assertDontSee('081200000000');
        $this->get(route('talents.document', [$talent, 'cv']))->assertOk()->assertHeader('content-disposition', 'inline; filename="rahmat-uji-cv.pdf"');

        $this->post('/profil/status', ['type' => 'alumni', 'graduation_year' => 2026, 'experience_since' => 2026])->assertRedirect(route('talents.show', $talent));
        $talent->refresh();
        $this->assertSame('alumni', $talent->type);
        $this->assertSame('Rahmat Uji', $talent->name); // no automatic "S.T."
    }

    public function test_hmts_defaults_to_pasif_and_consent_is_required(): void
    {
        $user = $this->talentUser();
        $this->actingAs($user)->post('/profil', ['type' => 'mahasiswa', 'name' => 'X'])->assertSessionHasErrors(['consent', 'phone', 'semester']);

        $this->post('/profil', $this->profilePayload(['type' => 'mahasiswa', 'semester' => 5, 'hmts_member' => '0', 'hmts_status' => 'pengurus']));
        $this->assertSame('pasif', $user->fresh()->talent->hmts_status);
    }

    public function test_talent_never_sees_recruit_button(): void
    {
        $other = Talent::where('availability', Availability::Tersedia)->first();

        $this->actingAs($this->talentUser())->get(route('talents.show', $other))
            ->assertDontSee('>Ajukan Rekrut<', false)->assertSee('hanya tersedia untuk akun perusahaan');
    }

    public function test_documents_are_private(): void
    {
        Storage::fake('local');
        $talent = Talent::first();
        $talent->update(['cv_path' => UploadedFile::fake()->create('cv.pdf', 10)->store('documents/cv', 'local')]);

        $this->get(route('talents.document', [$talent, 'cv']))->assertRedirect(route('login'));
        $this->actingAs($this->talentUser())->get(route('talents.document', [$talent, 'cv']))->assertForbidden();
        $this->actingAs($this->hrd())->get(route('talents.document', [$talent, 'cv']))->assertOk();
        $this->actingAs($this->hrd())->get(route('talents.document', [$talent, 'cv']).'?unduh=1')->assertDownload();
    }

    public function test_company_posts_job_and_admin_approves(): void
    {
        Storage::fake('local');
        $this->actingAs($this->hrd())->post('/lowongan/pasang', [
            'title' => 'Site Engineer Uji Coba', 'location' => 'Palu', 'duration_months' => 6,
            'description' => 'Mengawasi pekerjaan jalan dan menyusun laporan harian untuk paket uji coba.',
            'package' => 'tenaga_ahli', 'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect();

        $job = JobPosting::where('title', 'Site Engineer Uji Coba')->firstOrFail();
        $this->assertSame('menunggu_verifikasi', $job->status);
        $this->get('/lowongan')->assertDontSee('Site Engineer Uji Coba');
        $this->get(route('jobs.posting.status', $job))->assertOk()->assertSee('Menunggu verifikasi');

        // Another company cannot see this posting's status page.
        $other = User::factory()->create(['role' => 'perusahaan']);
        Company::create(['user_id' => $other->id, 'name' => 'PT Lain', 'type' => 'kontraktor', 'city' => 'Palu', 'status' => 'terverifikasi']);
        $this->actingAs($other)->get(route('jobs.posting.status', $job))->assertForbidden();

        $job->update(['status' => 'aktif', 'approved_at' => now()]); // what the Filament "Setujui" action does
        $this->get('/lowongan')->assertSee('Site Engineer Uji Coba');
    }

    public function test_demo_buttons_log_into_real_accounts(): void
    {
        $this->post('/demo/masuk', ['role' => 'perusahaan']);
        $this->assertAuthenticatedAs($this->hrd());
        $this->post('/keluar');

        $this->post('/demo/masuk', ['role' => 'talenta'])->assertRedirect(route('profile.edit'));
        $this->assertTrue(auth()->user()->isTalent());
        $this->assertNotNull(auth()->user()->email_verified_at);
    }
}
