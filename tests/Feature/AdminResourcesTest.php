<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Widgets\CompanyStats;
use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Widgets\JobApplicationStats;
use App\Filament\Resources\JobPostings\Pages\ListJobPostings;
use App\Filament\Resources\JobPostings\Widgets\JobPostingStats;
use App\Filament\Resources\RecruitmentOffers\Pages\ListRecruitmentOffers;
use App\Filament\Resources\RecruitmentOffers\Widgets\RecruitmentOfferStats;
use App\Filament\Resources\Talent\Pages\ListTalent;
use App\Filament\Resources\Talent\Widgets\TalentStats;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin resource pages: filters and the stat cards above each table. */
class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function company(array $attrs = []): Company
    {
        static $n = 0;
        $n++;

        return Company::create($attrs + ['name' => "PT Uji Admin $n", 'type' => 'konsultan', 'city' => 'Kota Uji Satu']);
    }

    private function job(Company $company, array $attrs = []): JobPosting
    {
        return JobPosting::create($attrs + [
            'company_id' => $company->id, 'title' => 'Lowongan Uji '.uniqid(), 'package' => 'reguler', 'status' => 'aktif',
            'location' => 'Palu', 'duration_months' => 3, 'description' => 'Uji.', 'closes_at' => today()->addDays(10),
        ]);
    }

    public function test_company_filters(): void
    {
        $inSatu = $this->company(['city' => 'Kota Uji Satu', 'legal_doc_path' => 'legal/a.pdf']);
        $inDua = $this->company(['city' => 'Kota Uji Dua', 'type' => 'kontraktor']);
        $this->job($inSatu);
        $this->job($inDua, ['status' => 'ditutup']);

        Livewire::test(ListCompanies::class)
            ->filterTable('city', 'Kota Uji Satu')
            ->assertCanSeeTableRecords([$inSatu])
            ->assertCanNotSeeTableRecords([$inDua]);

        Livewire::test(ListCompanies::class)
            ->filterTable('type', 'kontraktor')
            ->assertCanSeeTableRecords([$inDua])
            ->assertCanNotSeeTableRecords([$inSatu]);

        Livewire::test(ListCompanies::class)
            ->filterTable('legal_doc', true)
            ->assertCanSeeTableRecords([$inSatu])
            ->assertCanNotSeeTableRecords([$inDua]);

        Livewire::test(ListCompanies::class)
            ->filterTable('legal_doc', false)
            ->assertCanSeeTableRecords([$inDua])
            ->assertCanNotSeeTableRecords([$inSatu]);

        Livewire::test(ListCompanies::class)
            ->filterTable('has_live_job')
            ->assertCanSeeTableRecords([$inSatu])
            ->assertCanNotSeeTableRecords([$inDua]);

        $old = $this->company(['city' => 'Kota Uji Dua']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        Livewire::test(ListCompanies::class)
            ->filterTable('created_at', ['from' => today()->subDays(7)->toDateString(), 'until' => null])
            ->assertCanSeeTableRecords([$inSatu, $inDua])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_company_stats(): void
    {
        $this->company(['status' => 'menunggu']);
        $pending = Company::where('status', 'menunggu')->count();
        $live = JobPosting::open()->count();

        Livewire::test(ListCompanies::class)->assertSee('Menunggu verifikasi')->assertSee('perlu dicek');

        Livewire::test(CompanyStats::class)->assertSeeInOrder([
            'Total perusahaan', (string) Company::count(),
            'Menunggu verifikasi', (string) $pending, 'perlu dicek',
            'Terverifikasi', (string) Company::where('status', 'terverifikasi')->count(),
            'Lowongan tayang', (string) $live,
        ]);
    }

    public function test_job_posting_filters(): void
    {
        $company = $this->company(['status' => 'terverifikasi']);
        $live = $this->job($company, ['package' => 'tenaga_ahli', 'concentration' => 'Geoteknik']);
        $expired = $this->job($company, ['package' => 'magang', 'closes_at' => today()->subDay()]);
        $waiting = $this->job($company, ['status' => 'menunggu_verifikasi', 'payment_proof_path' => 'bukti/a.jpg']);
        $noProof = $this->job($company, ['status' => 'menunggu_verifikasi']);

        Livewire::test(ListJobPostings::class)
            ->filterTable('package', 'tenaga_ahli')
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$expired, $waiting]);

        Livewire::test(ListJobPostings::class)
            ->filterTable('concentration', 'Geoteknik')
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$expired]);

        Livewire::test(ListJobPostings::class)
            ->filterTable('live')
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$expired, $waiting, $noProof]);

        Livewire::test(ListJobPostings::class)
            ->filterTable('payment_pending')
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$live, $noProof]);

        Livewire::test(ListJobPostings::class)
            ->filterTable('location', 'Palu')
            ->assertCanSeeTableRecords([$live]);
    }

    public function test_job_posting_stats(): void
    {
        $company = $this->company(['status' => 'terverifikasi']);
        $this->job($company, ['package' => 'tenaga_ahli', 'approved_at' => now()]);
        $this->job($company, ['package' => 'magang', 'approved_at' => now()]);
        $this->job($company, ['package' => 'reguler', 'approved_at' => now()->subMonthsNoOverflow(2)]);
        $this->job($company, ['status' => 'menunggu_verifikasi', 'payment_proof_path' => 'bukti/b.jpg']);

        $revenue = JobPosting::whereBetween('approved_at', [now()->startOfMonth(), now()->endOfMonth()])->get()->sum(fn ($j) => $j->packagePrice());
        $this->assertGreaterThanOrEqual(250000, $revenue);

        Livewire::test(JobPostingStats::class)->assertSeeInOrder([
            'Menunggu cek bayar', (string) JobPosting::where('status', 'menunggu_verifikasi')->whereNotNull('payment_proof_path')->count(),
            'Tayang', (string) JobPosting::open()->count(),
            'Pendapatan bulan ini', 'Rp'.number_format($revenue, 0, ',', '.'),
            'Total pelamar', (string) JobApplication::count(),
        ]);

        Livewire::test(ListJobPostings::class)->assertSee('Pendapatan bulan ini');
    }

    private function talent(array $attrs = [], ?int $jenjang = null, ?string $expires = null): Talent
    {
        $talent = Talent::factory()->create($attrs + ['city' => 'Kota Uji Satu']);
        if ($jenjang) {
            $talent->certifications()->create([
                'jabatan_kerja' => 'Ahli Geoteknik', 'jenjang' => $jenjang, 'registration_number' => 'UJI-'.$talent->id,
                'issued_at' => today()->subYears(3), 'expires_at' => $expires ?? today()->addYear()->toDateString(),
            ]);
        }

        return $talent;
    }

    public function test_talent_filters(): void
    {
        $madya = $this->talent(['cv_path' => 'cv/a.pdf'], 8);
        $expired = $this->talent(['cv_path' => 'cv/b.pdf'], 5, today()->subDay()->toDateString());
        $hidden = $this->talent(['is_visible' => false]);

        Livewire::test(ListTalent::class)
            ->filterTable('jenjang', [8, 9])
            ->assertCanSeeTableRecords([$madya])
            ->assertCanNotSeeTableRecords([$expired, $hidden]);

        Livewire::test(ListTalent::class)
            ->filterTable('skk_expired')
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$madya, $hidden]);

        Livewire::test(ListTalent::class)
            ->filterTable('has_cv', false)
            ->assertCanSeeTableRecords([$hidden])
            ->assertCanNotSeeTableRecords([$madya, $expired]);

        Livewire::test(ListTalent::class)
            ->filterTable('is_visible', false)
            ->assertCanSeeTableRecords([$hidden])
            ->assertCanNotSeeTableRecords([$madya, $expired]);

        Livewire::test(ListTalent::class)
            ->filterTable('city', 'Kota Uji Satu')
            ->assertCanSeeTableRecords([$madya, $expired, $hidden]);

        Livewire::test(ListTalent::class)
            ->filterTable('availability', $madya->availability->value)
            ->assertCanSeeTableRecords([$madya]);

        Livewire::test(ListTalent::class)
            ->filterTable('concentration', $madya->concentration)
            ->assertCanSeeTableRecords([$madya]);
    }

    public function test_talent_stats(): void
    {
        $this->talent(['skk_scan_path' => 'skk/a.pdf'], 7);
        $this->talent(['cv_path' => 'cv/c.pdf', 'type' => 'mahasiswa']);

        $queue = Talent::whereNotNull('skk_scan_path')->whereNull('skk_verified_at')->whereNull('skk_review_note')->count();
        $total = Talent::count();
        $withCv = Talent::whereNotNull('cv_path')->count();

        Livewire::test(TalentStats::class)->assertSeeInOrder([
            'Total talenta', (string) $total,
            Talent::where('type', 'alumni')->count().' alumni', Talent::where('type', 'mahasiswa')->count().' mahasiswa',
            'Tersedia', (string) Talent::where('availability', 'tersedia')->count(),
            'SKK perlu dicek', (string) $queue,
            'Punya CV', round($withCv / $total * 100).'%', "$withCv dari $total",
        ]);

        Livewire::test(ListTalent::class)->assertSee('Punya CV');
    }

    public function test_job_application_filters_columns_and_detail(): void
    {
        $mine = $this->company(['name' => 'PT Lamaran Uji Satu', 'status' => 'terverifikasi']);
        $other = $this->company(['name' => 'PT Lamaran Uji Dua', 'status' => 'terverifikasi']);
        $mineJob = $this->job($mine, ['title' => 'Drafter Uji Satu']);
        $rejected = JobApplication::create([
            'job_posting_id' => $mineJob->id, 'talent_id' => $this->talent()->id, 'status' => 'ditolak',
            'message' => 'Saya tertarik dengan posisi ini.', 'company_note' => 'Pengalaman belum sesuai',
        ]);
        $offered = JobApplication::create([
            'job_posting_id' => $mineJob->id, 'talent_id' => $this->talent()->id, 'status' => 'tawaran_kerja',
            'job_offer_position' => 'Drafter Senior Uji', 'job_offer_start_date' => today()->addWeek(),
            'job_offered_at' => now(), 'job_offer_expires_at' => now()->addDays(7),
        ]);
        $elsewhere = JobApplication::create(['job_posting_id' => $this->job($other)->id, 'talent_id' => $this->talent()->id, 'status' => 'baru']);

        Livewire::test(ListJobApplications::class)
            ->filterTable('company', $mine->id)
            ->assertCanSeeTableRecords([$rejected, $offered])
            ->assertCanNotSeeTableRecords([$elsewhere]);

        Livewire::test(ListJobApplications::class)
            ->filterTable('job_posting_id', $mineJob->id)
            ->assertCanSeeTableRecords([$rejected, $offered])
            ->assertCanNotSeeTableRecords([$elsewhere]);

        Livewire::test(ListJobApplications::class)
            ->assertCanRenderTableColumn('company_note')
            ->assertCanRenderTableColumn('job_offer_expires_at')
            ->assertSee('Alasan tolak')->assertSee('Pengalaman belum sesuai');

        Livewire::test(ListJobApplications::class)
            ->mountTableAction('detail', $rejected)
            ->assertMountedActionModalSee(['Pengalaman belum sesuai', 'Saya tertarik dengan posisi ini.', 'Drafter Uji Satu', 'PT Lamaran Uji Satu'])
            ->assertMountedActionModalDontSee('Batas jawab');

        Livewire::test(ListJobApplications::class)
            ->mountTableAction('detail', $offered)
            ->assertMountedActionModalSee(['Drafter Senior Uji', 'Batas jawab'])
            ->assertMountedActionModalDontSee('Catatan perusahaan');
    }

    public function test_job_application_stats(): void
    {
        $company = $this->company(['status' => 'terverifikasi']);
        JobApplication::create(['job_posting_id' => $this->job($company)->id, 'talent_id' => $this->talent()->id, 'status' => 'baru']);
        JobApplication::create(['job_posting_id' => $this->job($company)->id, 'talent_id' => $this->talent()->id, 'status' => 'diambil']);

        Livewire::test(JobApplicationStats::class)->assertSeeInOrder([
            'Lamaran 30 hari', (string) JobApplication::where('created_at', '>=', now()->subDays(30))->count(),
            'Baru belum ditinjau', (string) JobApplication::where('status', 'baru')->count(),
            'Tawaran kerja berjalan', (string) JobApplication::where('status', 'tawaran_kerja')->count(),
            'Diambil', (string) JobApplication::where('status', 'diambil')->count(),
        ]);

        Livewire::test(ListJobApplications::class)->assertSee('Baru belum ditinjau');
    }

    private function offer(Company $company, array $attrs = []): RecruitmentOffer
    {
        return RecruitmentOffer::create($attrs + [
            'talent_id' => $this->talent()->id, 'company_id' => $company->id, 'company_name' => $company->name,
            'contact_name' => 'Budi Uji', 'contact_email' => 'budi@uji.test', 'position' => 'Pelaksana Uji', 'message' => 'Halo.', 'status' => 'menunggu',
        ]);
    }

    public function test_recruitment_offer_help_filter_and_stats(): void
    {
        Livewire::test(ListRecruitmentOffers::class)
            ->assertActionExists('bantuan')
            ->assertSee('Apa ini?')
            ->mountAction('bantuan')
            ->assertMountedActionModalSee(['Menunggu jawaban', 'Tertarik', 'Tawaran kerja', 'Diambil', 'Kedaluwarsa', 'Cari Talenta', 'Admin hanya memantau']);

        $mine = $this->company(['name' => 'PT Rekrut Uji Satu', 'status' => 'terverifikasi']);
        $other = $this->company(['name' => 'PT Rekrut Uji Dua', 'status' => 'terverifikasi']);
        $interested = $this->offer($mine, ['status' => 'diterima', 'responded_at' => now()]);
        $taken = $this->offer($mine, ['status' => 'diambil', 'responded_at' => now(), 'job_offered_at' => now()]);
        $declined = $this->offer($other, ['status' => 'ditolak', 'responded_at' => now()]);

        Livewire::test(ListRecruitmentOffers::class)
            ->filterTable('company_id', $mine->id)
            ->assertCanSeeTableRecords([$interested, $taken])
            ->assertCanNotSeeTableRecords([$declined]);

        $everInterested = RecruitmentOffer::whereNotNull('responded_at')
            ->where(fn ($q) => $q->where('status', '!=', 'ditolak')->orWhereNotNull('job_offered_at'))->count();

        Livewire::test(RecruitmentOfferStats::class)->assertSeeInOrder([
            'Terkirim', (string) RecruitmentOffer::count(),
            'Tertarik', (string) $everInterested,
            'Tawaran kerja', (string) RecruitmentOffer::whereNotNull('job_offered_at')->count(),
            'Diambil', (string) RecruitmentOffer::where('status', 'diambil')->count(),
        ]);

        Livewire::test(ListRecruitmentOffers::class)->assertSee('Terkirim')
            ->assertSee('Talenta setuju dihubungi'); // status tooltip
    }
}
