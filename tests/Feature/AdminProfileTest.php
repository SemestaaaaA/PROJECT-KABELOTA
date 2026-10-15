<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\JobPostings\Pages\ListJobPostings;
use App\Filament\Resources\Talent\Pages\ListTalent;
use App\Models\AdminActivity;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin 4c: activity log, custom profile page (photo, notification preferences, sessions). */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function pendingCompany(): Company
    {
        return Company::create(['name' => 'PT Uji Riwayat', 'type' => 'konsultan', 'city' => 'Palu', 'status' => 'menunggu']);
    }

    public function test_verifying_a_company_records_an_activity(): void
    {
        $admin = $this->admin();
        $company = $this->pendingCompany();
        $this->actingAs($admin);

        Livewire::test(ListCompanies::class)->callTableAction('verifikasi', $company);

        $activity = AdminActivity::sole();
        $this->assertSame('company_verified', $activity->action);
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertTrue($activity->subject->is($company));
        $this->assertStringContainsString('PT Uji Riwayat', $activity->description);
    }

    public function test_approving_a_job_and_rejecting_skk_record_activities(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $job = JobPosting::create([
            'company_id' => $this->pendingCompany()->id, 'title' => 'Lowongan Uji Riwayat', 'package' => 'reguler', 'status' => 'menunggu_verifikasi',
            'location' => 'Palu', 'duration_months' => 3, 'description' => 'Uji.', 'closes_at' => today()->addDays(10),
        ]);
        $talent = Talent::factory()->create(['skk_scan_path' => 'skk/uji.pdf', 'skk_review_note' => null]);

        Livewire::test(ListJobPostings::class)->callTableAction('setujui', $job);
        Livewire::test(ListTalent::class)->callTableAction('tolak_skk', $talent, data: ['note' => 'Nomor registrasi tidak terbaca.']);

        $approved = AdminActivity::where('action', 'job_approved')->sole();
        $this->assertTrue($approved->subject->is($job));
        $this->assertSame($admin->id, $approved->user_id);

        $rejected = AdminActivity::where('action', 'skk_rejected')->sole();
        $this->assertTrue($rejected->subject->is($talent));
        $this->assertStringContainsString('Nomor registrasi tidak terbaca.', $rejected->description);
    }

    public function test_activity_log_page_lists_entries_for_admins_only(): void
    {
        $admin = $this->admin();
        $company = $this->pendingCompany();
        $this->actingAs($admin);
        Livewire::test(ListCompanies::class)->callTableAction('verifikasi', $company);
        $activity = AdminActivity::sole();

        Livewire::test(ListAdminActivities::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee('Perusahaan diverifikasi')
            ->assertSee('PT Uji Riwayat');
        $this->get('/admin/admin-activities')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'talenta']))->get('/admin/admin-activities')->assertForbidden();
    }
}
