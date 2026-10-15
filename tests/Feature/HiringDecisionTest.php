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
use Tests\TestCase;

class HiringDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hrd(): User
    {
        return User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
    }

    /** @return array{0: User, 1: Talent} */
    private function talent(): array
    {
        $user = User::factory()->create(['role' => 'talenta']);
        $talent = Talent::factory()->create(['user_id' => $user->id, 'availability' => Availability::Tersedia, 'phone' => '081299990000', 'email' => 'rahasia@contoh.id']);

        return [$user, $talent];
    }

    private function interestedOffer(Talent $talent, ?Company $company = null, string $status = 'diterima'): RecruitmentOffer
    {
        $company ??= $this->hrd()->company;

        return RecruitmentOffer::create([
            'talent_id' => $talent->id, 'company_id' => $company->id, 'company_name' => $company->name,
            'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Site Engineer Uji',
            'message' => 'Pesan tawaran uji coba yang cukup panjang.', 'status' => $status,
        ]);
    }

    /** A second company with its own HRD account and an open job. @return array{0: User, 1: JobPosting} */
    private function otherCompany(): array
    {
        $job = JobPosting::open()->where('company_id', '!=', $this->hrd()->company->id)->firstOrFail();
        $user = User::factory()->create(['role' => 'perusahaan']);
        $job->company->update(['user_id' => $user->id]);

        return [$user, $job];
    }

    public function test_status_helpers(): void
    {
        $interested = new RecruitmentOffer(['status' => 'diterima']);
        $this->assertSame('Tertarik', $interested->statusLabel());
        $this->assertTrue($interested->canOfferJob());
        $this->assertTrue($interested->contactVisible());

        $reviewed = new JobApplication(['status' => 'ditinjau']);
        $this->assertTrue($reviewed->canOfferJob());
        $this->assertFalse($reviewed->contactVisible());

        $offered = new JobApplication(['status' => 'tawaran_kerja']);
        $this->assertSame('b-offer', $offered->badgeClass());
        $this->assertTrue($offered->contactVisible());
        $this->assertSame('Ditutup', (new JobApplication(['status' => 'ditutup']))->statusLabel());
    }

    public function test_old_accepted_applications_become_job_offers(): void
    {
        $job = JobPosting::open()->firstOrFail();
        [, $talent] = $this->talent();
        $application = JobApplication::create(['job_posting_id' => $job->id, 'talent_id' => $talent->id, 'status' => 'diterima']);

        $migration = require database_path('migrations/2026_10_15_000001_add_job_offer_columns.php');
        $migration->convertAcceptedApplications();

        $application->refresh();
        $this->assertSame('tawaran_kerja', $application->status);
        $this->assertSame($job->title, $application->job_offer_position);
        $this->assertTrue($application->job_offer_expires_at->isFuture());
    }
}
