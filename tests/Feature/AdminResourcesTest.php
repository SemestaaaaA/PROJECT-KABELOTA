<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Widgets\CompanyStats;
use App\Filament\Resources\JobPostings\Pages\ListJobPostings;
use App\Filament\Resources\JobPostings\Widgets\JobPostingStats;
use App\Models\JobApplication;
use App\Models\Company;
use App\Models\JobPosting;
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
}
