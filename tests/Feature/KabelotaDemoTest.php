<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\ApplicationReceived;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\OfferAnswered;
use App\Notifications\OfferReceived;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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
        foreach (['/admin/talent', '/admin/job-applications', '/admin/recruitment-offers', '/admin/contact-messages'] as $page) {
            $this->get($page)->assertOk();
        }
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

        // QA: duration is always whole months; free text like "1 tahun" is rejected.
        $base = ['company_name' => 'CV Uji', 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Drafter', 'message' => 'Kami membutuhkan drafter untuk paket gedung sekolah.'];
        $this->post(route('offers.store', $talent), $base + ['duration' => '1 tahun'])->assertSessionHasErrorsIn('offer', 'duration');
        $this->post(route('offers.store', $talent), $base + ['duration' => '24'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recruitment_offers', ['talent_id' => $talent->id, 'position' => 'Drafter', 'duration' => '24 bulan']);
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

    /** Talent with a profile, for inbox / apply tests. */
    private function talentWithProfile(): array
    {
        $user = $this->talentUser();
        $talent = Talent::factory()->create(['user_id' => $user->id, 'availability' => Availability::Tersedia, 'phone' => '081299990000', 'email' => 'rahasia@contoh.id']);

        return [$user, $talent];
    }

    public function test_talent_accepts_offer_and_company_sees_contact(): void
    {
        [$user, $talent] = $this->talentWithProfile();
        $hrd = $this->hrd();
        $offer = RecruitmentOffer::create([
            'talent_id' => $talent->id, 'company_id' => $hrd->company->id, 'company_name' => $hrd->company->name,
            'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Site Engineer Uji', 'message' => 'Pesan tawaran uji coba yang cukup panjang.',
        ]);

        $this->actingAs($hrd)->get('/perusahaan/tawaran')->assertOk()->assertSee('Site Engineer Uji')->assertDontSee('081299990000');

        // Another talent cannot answer this offer.
        $this->actingAs($this->talentUser())->post(route('talent.offers.respond', $offer), ['decision' => 'diterima'])->assertForbidden();

        $this->actingAs($user)->get('/tawaran')->assertOk()->assertSee('Site Engineer Uji')->assertSee('Terima');
        $this->post(route('talent.offers.respond', $offer), ['decision' => 'diterima'])->assertRedirect('/tawaran');
        $this->assertSame('diterima', $offer->fresh()->status);
        $this->post(route('talent.offers.respond', $offer), ['decision' => 'ditolak'])->assertStatus(422);

        $this->actingAs($hrd)->get('/perusahaan/tawaran')->assertSee('081299990000')->assertSee('rahasia@contoh.id');
    }

    public function test_rejected_offer_keeps_contact_hidden(): void
    {
        [$user, $talent] = $this->talentWithProfile();
        $hrd = $this->hrd();
        $offer = RecruitmentOffer::create([
            'talent_id' => $talent->id, 'company_id' => $hrd->company->id, 'company_name' => $hrd->company->name,
            'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Drafter Uji', 'message' => 'Pesan tawaran uji coba yang cukup panjang.',
        ]);

        $this->actingAs($user)->post(route('talent.offers.respond', $offer), ['decision' => 'ditolak', 'note' => 'Masih kontrak']);
        $this->actingAs($hrd)->get('/perusahaan/tawaran')->assertSee('Masih kontrak')->assertDontSee('081299990000');
    }

    public function test_talent_applies_once_and_company_manages_applicant(): void
    {
        [$user, $talent] = $this->talentWithProfile();
        $hrd = $this->hrd();
        $job = $hrd->company->jobPostings()->open()->firstOrFail();

        $this->actingAs($user)->post(route('jobs.apply', $job), ['message' => 'Siap ditempatkan.'])->assertRedirect('/lamaran');
        $this->post(route('jobs.apply', $job))->assertSessionHas('status', 'Anda sudah melamar lowongan ini sebelumnya.');
        $this->assertSame(1, JobApplication::where('talent_id', $talent->id)->count());
        $this->get('/lamaran')->assertOk()->assertSee($job->title);
        $this->get('/lowongan')->assertSee('Sudah melamar');

        $application = JobApplication::where('talent_id', $talent->id)->first();
        $this->actingAs($hrd)->get(route('company.applicants', $job))->assertOk()->assertSee(e($talent->name), false)->assertDontSee('081299990000');
        $this->post(route('company.applications.update', $application), ['status' => 'diterima'])->assertRedirect();
        $this->get(route('company.applicants', $job))->assertSee('081299990000');

        $this->actingAs($user)->get('/lamaran')->assertSee('Diterima');
    }

    public function test_other_company_cannot_see_or_change_applicants(): void
    {
        $hrd = $this->hrd();
        $job = $hrd->company->jobPostings()->open()->firstOrFail();
        $application = JobApplication::where('job_posting_id', $job->id)->firstOrFail();

        $other = User::factory()->create(['role' => 'perusahaan']);
        Company::create(['user_id' => $other->id, 'name' => 'PT Lain', 'type' => 'kontraktor', 'city' => 'Palu', 'status' => 'terverifikasi']);

        $this->actingAs($other)->get(route('company.applicants', $job))->assertForbidden();
        $this->actingAs($other)->post(route('company.applications.update', $application), ['status' => 'ditolak'])->assertForbidden();
    }

    public function test_talent_without_profile_is_sent_to_profile_before_applying(): void
    {
        $job = JobPosting::open()->firstOrFail();

        $this->actingAs($this->talentUser())->post(route('jobs.apply', $job))->assertRedirect(route('profile.edit'));
        $this->get('/tawaran')->assertRedirect(route('profile.edit'));
    }

    public function test_account_pages_are_role_gated(): void
    {
        $this->actingAs($this->hrd())->get('/tawaran')->assertRedirect('/');
        $this->actingAs($this->talentUser())->get('/perusahaan/lowongan')->assertRedirect('/');
        $this->actingAs($this->hrd())->get('/perusahaan/lowongan')->assertOk()->assertSee('pelamar');
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin/job-applications')->assertOk();
    }

    public function test_emails_are_queued_for_every_step(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talentWithProfile();
        $hrd = $this->hrd();

        $this->actingAs($hrd)->post(route('offers.store', $talent), [
            'company_name' => $hrd->company->name, 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id',
            'position' => 'Site Engineer', 'message' => 'Kami membutuhkan site engineer untuk paket jalan.',
        ]);
        Notification::assertSentTo($user, OfferReceived::class);

        $offer = RecruitmentOffer::where('talent_id', $talent->id)->firstOrFail();
        $this->actingAs($user)->post(route('talent.offers.respond', $offer), ['decision' => 'diterima']);
        Notification::assertSentTo($hrd, OfferAnswered::class);

        $job = $hrd->company->jobPostings()->open()->firstOrFail();
        $this->actingAs($user)->post(route('jobs.apply', $job));
        Notification::assertSentTo($hrd, ApplicationReceived::class);

        $application = JobApplication::where('talent_id', $talent->id)->firstOrFail();
        $this->actingAs($hrd)->post(route('company.applications.update', $application), ['status' => 'ditinjau']);
        Notification::assertSentTo($user, ApplicationStatusChanged::class);
    }

    public function test_offer_email_renders_in_indonesian(): void
    {
        [$user, $talent] = $this->talentWithProfile();
        $offer = RecruitmentOffer::create([
            'talent_id' => $talent->id, 'company_id' => $this->hrd()->company->id, 'company_name' => 'CV Lembah Palu Konsultan',
            'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Site Engineer Jalan', 'message' => 'Pesan tawaran uji coba.',
        ]);

        $html = (string) (new OfferReceived($offer))->toMail($user)->render();
        $this->assertStringContainsString('Tawaran', $html);
        $this->assertStringContainsString('Site Engineer Jalan', $html);
        $this->assertStringContainsString(route('talent.offers'), $html);
    }

    public function test_forgot_password_flow(): void
    {
        Notification::fake();
        $user = $this->talentUser(['email' => 'lupa@contoh.id']);

        $this->get('/lupa-sandi')->assertOk();
        $this->post('/lupa-sandi', ['email' => 'tidakada@contoh.id'])->assertSessionHas('status');
        $this->post('/lupa-sandi', ['email' => 'lupa@contoh.id'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) { $token = $n->token; return true; });

        $this->get('/reset-sandi/'.$token.'?email=lupa@contoh.id')->assertOk();
        $this->post('/reset-sandi', ['token' => $token, 'email' => 'lupa@contoh.id', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertRedirect('/');
        $this->post('/masuk', ['email' => 'lupa@contoh.id', 'password' => 'baru12345']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_job_detail_company_page_and_legal_pages(): void
    {
        $job = JobPosting::open()->with('company')->firstOrFail();

        $this->get(route('jobs.show', $job))->assertOk()->assertSee(e($job->title), false)->assertSee($job->company->name);
        $this->get(route('companies.show', $job->company))->assertOk()->assertSee('Terverifikasi Kabelota')->assertSee(e($job->title), false);

        $pending = JobPosting::create($job->only(['company_id', 'package', 'location', 'duration_months', 'description', 'closes_at']) + ['title' => 'Belum tayang', 'status' => 'menunggu_verifikasi']);
        $this->get(route('jobs.show', $pending))->assertNotFound();

        $this->get('/kebijakan-privasi')->assertOk()->assertSee('UU PDP');
        $this->get('/syarat-penggunaan')->assertOk()->assertSee('Hukum yang berlaku');
    }

    public function test_branded_error_page_and_share_preview(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertSee('Ke beranda');

        $this->get('/')->assertOk()
            ->assertSee('og:image', false)
            ->assertSee('/images/og.jpg', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Mode demo.');
    }

    public function test_qa_server_shows_qa_ribbon_with_report_link(): void
    {
        $this->app['env'] = 'staging';
        config(['kabelota.qa_form_url' => 'https://forms.gle/contoh']);

        $this->get('/')->assertOk()
            ->assertSee('Versi QA.')
            ->assertSee('https://forms.gle/contoh', false)
            ->assertDontSee('Mode demo.');
    }

    public function test_make_admin_command_promotes_registered_member(): void
    {
        $user = $this->talentUser(['email' => 'anggota@contoh.id']);

        $this->artisan('kabelota:make-admin', ['email' => 'anggota@contoh.id'])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);

        $this->artisan('kabelota:make-admin', ['email' => 'tidak-ada@contoh.id'])->assertFailed();
    }

    public function test_layout_ships_loader_progress_bar_and_skeleton_hooks(): void
    {
        // Loader markup is always present; the head script decides (once per session) whether it shows.
        $this->get('/')->assertOk()
            ->assertSee('id="intro"', false)
            ->assertSee("sessionStorage.getItem('kb-intro')", false)
            ->assertSee('prefers-reduced-motion: reduce', false)
            ->assertSee('class="nav-progress"', false);

        $this->get('/talenta')->assertSee('data-skeleton="talent:9"', false);
        $this->get('/talenta?view=list')->assertSee('data-skeleton="row:8"', false);
        $this->get('/lowongan')->assertSee('data-skeleton="job:6"', false);

        // Error pages use their own light layout without the loader.
        $this->get('/tidak-ada')->assertNotFound()->assertDontSee('id="intro"', false);
    }
}
