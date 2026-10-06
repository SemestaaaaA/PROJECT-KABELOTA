<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page renders without lazy-loaded relations (N+1 queries), for each role. */
class NoLazyLoadingTest extends TestCase
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

    public function test_pages_render_without_lazy_loading(): void
    {
        $talent = Talent::whereHas('offers')->firstOrFail();
        $talentUser = User::factory()->create(['role' => 'talenta']);
        $talent->update(['user_id' => $talentUser->id]);
        $hrd = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $job = $hrd->company->jobPostings()->firstOrFail();
        JobApplication::firstOrCreate(['job_posting_id' => $job->id, 'talent_id' => $talent->id], ['status' => 'baru']);

        $public = ['/', '/talenta', '/talenta?view=list', route('talents.show', $talent), '/lowongan', route('jobs.show', $job), route('companies.show', $hrd->company), '/tentang', '/kontak', '/untuk-perusahaan'];
        foreach ($public as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/profil', '/tawaran', '/lamaran', '/akun', route('talents.show', $talent)] as $url) {
            $this->actingAs($talentUser)->get($url)->assertOk();
        }

        foreach (['/perusahaan/lowongan', '/perusahaan/tawaran', '/perusahaan/profil', route('company.applicants', $job), '/lowongan/pasang', route('talents.show', $talent)] as $url) {
            $this->actingAs($hrd)->get($url)->assertOk();
        }

        foreach (['/admin', '/admin/companies', '/admin/job-postings', '/admin/talent', '/admin/job-applications', '/admin/recruitment-offers', '/admin/contact-messages'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->assertTrue(RecruitmentOffer::exists());
    }
}
