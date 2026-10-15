<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Widgets\CompanyStats;
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
}
