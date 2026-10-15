<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Widgets\CompanyStats;
use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Widgets\JobApplicationStats;
use App\Filament\Resources\JobPostings\JobPostingResource;
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
use Filament\Forms\Components\Select;
use Filament\Widgets\StatsOverviewWidget\Stat;
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

    /**
     * The widget's cards keyed by label. Reads Stat objects directly: asserting on rendered HTML would also
     * match digits inside SVG paths and wire ids.
     *
     * @return array<string, Stat>
     */
    private function cards(string $widget): array
    {
        $instance = Livewire::test($widget)->instance();
        $method = new \ReflectionMethod($instance, 'getStats');

        return collect($method->invoke($instance))->keyBy(fn ($stat) => (string) $stat->getLabel())->all();
    }

    /** @return array<string, mixed> label => value */
    private function values(string $widget): array
    {
        return array_map(fn ($stat) => $stat->getValue(), $this->cards($widget));
    }

    /** Every whole number in a card's description, e.g. "12 alumni · 3 mahasiswa" => [12, 3]. */
    private function numbersIn(string $widget, string $label): array
    {
        preg_match_all('/\d+/', (string) $this->cards($widget)[$label]->getDescription(), $m);

        return array_map('intval', $m[0]);
    }

    private function rupiahToInt(string $value): int
    {
        return (int) preg_replace('/\D/', '', $value);
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
        $before = $this->values(CompanyStats::class);

        $verified = $this->company(['status' => 'terverifikasi']);
        $this->company(['status' => 'menunggu']);
        $this->company(['status' => 'ditolak']);
        $this->job($verified);                                         // tayang
        $this->job($verified, ['closes_at' => today()->subDay()]);    // aktif but past closing: not tayang
        $this->job($verified, ['status' => 'menunggu_verifikasi']);   // not tayang

        $this->assertSame([
            'Total perusahaan' => $before['Total perusahaan'] + 3,
            'Menunggu verifikasi' => $before['Menunggu verifikasi'] + 1,
            'Terverifikasi' => $before['Terverifikasi'] + 1,
            'Lowongan tayang' => $before['Lowongan tayang'] + 1,
        ], $this->values(CompanyStats::class));
        $this->assertSame('perlu dicek', $this->cards(CompanyStats::class)['Menunggu verifikasi']->getDescription());

        Livewire::test(ListCompanies::class)->assertSee('Total perusahaan');
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

        // "aktif" only means approved; whether it is still tayang also depends on closes_at.
        $this->assertSame('Aktif (disetujui)', JobPostingResource::STATUSES['aktif']);
        Livewire::test(ListJobPostings::class)
            ->filterTable('status', 'aktif')
            ->assertCanSeeTableRecords([$live, $expired])
            ->assertCanNotSeeTableRecords([$waiting]);

        Livewire::test(ListJobPostings::class)
            ->filterTable('location', 'Palu')
            ->assertCanSeeTableRecords([$live]);
    }

    public function test_job_posting_stats(): void
    {
        $before = $this->values(JobPostingStats::class);

        $company = $this->company(['status' => 'terverifikasi']);
        $expert = $this->job($company, ['package' => 'tenaga_ahli', 'approved_at' => now()]);       // +200.000, tayang
        $this->job($company, ['package' => 'magang', 'approved_at' => now()]);                    // +50.000, tayang
        $this->job($company, ['package' => 'reguler', 'approved_at' => now()->subMonthsNoOverflow(2), 'closes_at' => today()->subMonth()]);
        $this->job($company, ['status' => 'menunggu_verifikasi', 'payment_proof_path' => 'bukti/b.jpg']);
        $this->job($company, ['status' => 'menunggu_verifikasi']);                                // no proof yet: not counted
        JobApplication::create(['job_posting_id' => $expert->id, 'talent_id' => $this->talent()->id, 'status' => 'baru']);

        $after = $this->values(JobPostingStats::class);

        $this->assertSame($before['Menunggu cek bayar'] + 1, $after['Menunggu cek bayar']);
        $this->assertSame($before['Tayang'] + 2, $after['Tayang']);
        $this->assertSame($this->rupiahToInt($before['Pendapatan bulan ini']) + 200000 + 50000, $this->rupiahToInt($after['Pendapatan bulan ini']));
        $this->assertMatchesRegularExpression('/^Rp\d{1,3}(\.\d{3})*$/', $after['Pendapatan bulan ini']);
        $this->assertSame($before['Total pelamar'] + 1, $after['Total pelamar']);

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
        $before = $this->values(TalentStats::class);
        [$alumni, $students] = $this->numbersIn(TalentStats::class, 'Total talenta');
        [$withCv, $total] = $this->numbersIn(TalentStats::class, 'Punya CV');

        $this->talent(['type' => 'alumni', 'availability' => 'tersedia', 'skk_scan_path' => 'skk/a.pdf'], 7);              // in SKK queue
        $this->talent(['type' => 'alumni', 'availability' => 'tersedia', 'skk_scan_path' => 'skk/b.pdf', 'skk_verified_at' => now()], 7);
        $this->talent(['type' => 'alumni', 'availability' => 'tidak_tersedia', 'skk_scan_path' => 'skk/c.pdf', 'skk_review_note' => 'Buram.'], 7);
        $this->talent(['type' => 'mahasiswa', 'availability' => 'terikat_kontrak', 'cv_path' => 'cv/c.pdf']);

        $after = $this->values(TalentStats::class);

        $this->assertSame($before['Total talenta'] + 4, $after['Total talenta']);
        $this->assertSame([$alumni + 3, $students + 1], $this->numbersIn(TalentStats::class, 'Total talenta'));
        $this->assertSame($before['Tersedia'] + 2, $after['Tersedia']);
        $this->assertSame($before['SKK perlu dicek'] + 1, $after['SKK perlu dicek']);
        $this->assertSame([$withCv + 1, $total + 4], $this->numbersIn(TalentStats::class, 'Punya CV'));
        $this->assertSame(round(($withCv + 1) / ($total + 4) * 100).'%', $after['Punya CV']);

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

        // Same title at another company: the filter option names the company so the two can be told apart.
        $twin = $this->job($other, ['title' => 'Drafter Uji Satu']);
        $select = Livewire::test(ListJobApplications::class)->instance()->getTableFiltersForm()->getComponent(
            fn ($c) => $c instanceof Select && $c->getStatePath() === 'tableDeferredFilters.job_posting_id.value',
        );
        $this->assertSame(
            [$mineJob->id => 'Drafter Uji Satu (PT Lamaran Uji Satu)', $twin->id => 'Drafter Uji Satu (PT Lamaran Uji Dua)'],
            $select->getSearchResults('Drafter Uji Satu'),
        );

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
        $before = $this->values(JobApplicationStats::class);

        $company = $this->company(['status' => 'terverifikasi']);
        foreach (['baru', 'tawaran_kerja', 'diambil', 'ditolak'] as $status) {
            JobApplication::create(['job_posting_id' => $this->job($company)->id, 'talent_id' => $this->talent()->id, 'status' => $status]);
        }
        $old = JobApplication::create(['job_posting_id' => $this->job($company)->id, 'talent_id' => $this->talent()->id, 'status' => 'baru']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->assertSame([
            'Lamaran 30 hari' => $before['Lamaran 30 hari'] + 4,
            'Baru belum ditinjau' => $before['Baru belum ditinjau'] + 2,
            'Tawaran kerja berjalan' => $before['Tawaran kerja berjalan'] + 1,
            'Diambil' => $before['Diambil'] + 1,
        ], $this->values(JobApplicationStats::class));

        Livewire::test(ListJobApplications::class)->assertSee('Baru belum ditinjau');
    }

    private function offer(Company $company, array $attrs = []): RecruitmentOffer
    {
        return RecruitmentOffer::create($attrs + [
            'talent_id' => $this->talent()->id, 'company_id' => $company->id, 'company_name' => $company->name,
            'contact_name' => 'Budi Uji', 'contact_email' => 'budi@uji.test', 'position' => 'Pelaksana Uji', 'message' => 'Halo.', 'status' => 'menunggu',
        ]);
    }

    public function test_recruitment_offer_help_and_filter(): void
    {
        Livewire::test(ListRecruitmentOffers::class)
            ->assertActionExists('bantuan')
            ->assertSee('Apa ini?')
            ->mountAction('bantuan')
            ->assertMountedActionModalSee(['Menunggu jawaban', 'Tertarik', 'Tawaran kerja', 'Diambil', 'Kedaluwarsa', 'Cari Talenta', 'Admin hanya memantau', 'Proses lain milik talenta ini ditutup.']);

        $mine = $this->company(['name' => 'PT Rekrut Uji Satu', 'status' => 'terverifikasi']);
        $other = $this->company(['name' => 'PT Rekrut Uji Dua', 'status' => 'terverifikasi']);
        $interested = $this->offer($mine, ['status' => 'diterima', 'responded_at' => now()]);
        $taken = $this->offer($mine, ['status' => 'diambil', 'responded_at' => now(), 'job_offered_at' => now()]);
        $declined = $this->offer($other, ['status' => 'ditolak', 'responded_at' => now()]);

        Livewire::test(ListRecruitmentOffers::class)
            ->filterTable('company_id', $mine->id)
            ->assertCanSeeTableRecords([$interested, $taken])
            ->assertCanNotSeeTableRecords([$declined]);

        Livewire::test(ListRecruitmentOffers::class)->assertSee('Terkirim')
            ->assertSee('Talenta setuju dihubungi'); // status tooltip
    }

    public function test_recruitment_offer_stats(): void
    {
        $before = $this->values(RecruitmentOfferStats::class);
        $company = $this->company(['status' => 'terverifikasi']);

        $this->offer($company, ['status' => 'menunggu']);                                                            // not answered: not counted
        $this->offer($company, ['status' => 'ditolak', 'responded_at' => now()]);                                    // declined outright: not counted
        $this->offer($company, ['status' => 'diterima', 'responded_at' => now()]);                                   // tertarik
        $this->offer($company, ['status' => 'ditolak', 'responded_at' => now(), 'job_offered_at' => now()]);         // was tertarik, declined the job offer
        $this->offer($company, ['status' => 'diambil', 'responded_at' => now(), 'job_offered_at' => now()]);         // tertarik, took the job

        $this->assertSame([
            'Terkirim' => $before['Terkirim'] + 5,
            'Tertarik' => $before['Tertarik'] + 3,
            'Tawaran kerja' => $before['Tawaran kerja'] + 2,
            'Diambil' => $before['Diambil'] + 1,
        ], $this->values(RecruitmentOfferStats::class));
    }
}
