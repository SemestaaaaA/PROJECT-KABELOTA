<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Models\User;
use App\Notifications\JobOfferAnswered;
use App\Notifications\JobOfferExpired;
use App\Notifications\JobOfferReceived;
use App\Notifications\JobOfferReminder;
use App\Notifications\ProcessClosed;
use App\Services\HiringDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        $otherJob = JobPosting::open()->where('id', '!=', $job->id)->firstOrFail();
        $reviewed = JobApplication::create(['job_posting_id' => $otherJob->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $offer = $this->interestedOffer($talent);

        $migration = require database_path('migrations/2026_10_15_000001_add_job_offer_columns.php');
        $migration->convertAcceptedApplications();

        $application->refresh();
        $this->assertSame('tawaran_kerja', $application->status);
        $this->assertSame($job->title, $application->job_offer_position);
        $this->assertNotNull($application->job_offered_at);
        $this->assertTrue($application->job_offer_expires_at->isFuture());
        $this->assertSame('ditinjau', $reviewed->fresh()->status);
        $this->assertSame('diterima', $offer->fresh()->status);
    }

    private function offerJob(RecruitmentOffer|JobApplication $record): void
    {
        app(HiringDecision::class)->offerJob($record, [
            'job_offer_position' => 'Site Engineer', 'job_offer_start_date' => today()->addWeek()->toDateString(),
        ]);
    }

    public function test_offer_job_needs_interest_and_notifies_talent(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $offer = $this->interestedOffer($talent, status: 'menunggu');

        $this->assertThrows(fn () => $this->offerJob($offer), HttpException::class);

        $offer->update(['status' => 'diterima']);
        $this->offerJob($offer);

        $offer->refresh();
        $this->assertSame('tawaran_kerja', $offer->status);
        $this->assertSame('Site Engineer', $offer->job_offer_position);
        $this->assertSame(7, $offer->jobOfferDaysLeft());
        Notification::assertSentTo($user, JobOfferReceived::class);
    }

    public function test_taking_one_job_offer_closes_every_other_process(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        $hrd = $this->hrd();
        [$otherUser, $otherJob] = $this->otherCompany();

        $fromHrd = $this->interestedOffer($talent);
        $hrdApplication = JobApplication::create(['job_posting_id' => $hrd->company->jobPostings()->open()->firstOrFail()->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $chosen = JobApplication::create(['job_posting_id' => $otherJob->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $this->offerJob($chosen);

        app(HiringDecision::class)->accept($chosen->fresh(), 'Terima kasih, sampai jumpa.');

        $this->assertSame('diambil', $chosen->fresh()->status);
        foreach ([$fromHrd, $hrdApplication] as $record) {
            $record->refresh();
            $this->assertSame('ditutup', $record->status);
            $this->assertSame('Terima kasih, sampai jumpa.', $record->closed_note);
            $this->assertNotNull($record->closed_at);
        }
        $this->assertSame(Availability::TerikatKontrak, $talent->fresh()->availability);
        Notification::assertSentTo($otherUser, JobOfferAnswered::class);
        // One email per company, even though two of its processes closed.
        Notification::assertSentToTimes($hrd, ProcessClosed::class, 1);
        Notification::assertSentTo($hrd, ProcessClosed::class, fn (ProcessClosed $n) => $n->farewell === 'Terima kasih, sampai jumpa.' && count($n->positions) === 2);
    }

    public function test_declining_a_job_offer_leaves_other_processes_open(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        $offer = $this->interestedOffer($talent);
        $other = JobApplication::create(['job_posting_id' => JobPosting::open()->firstOrFail()->id, 'talent_id' => $talent->id, 'status' => 'baru']);
        $this->offerJob($offer);

        app(HiringDecision::class)->decline($offer->fresh(), 'Lokasi terlalu jauh');

        $this->assertSame('ditolak', $offer->fresh()->status);
        $this->assertSame('Lokasi terlalu jauh', $offer->fresh()->closed_note);
        $this->assertSame('baru', $other->fresh()->status);
        Notification::assertSentTo($this->hrd(), JobOfferAnswered::class);
    }

    public function test_second_take_is_refused_once_one_offer_was_taken(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        [, $otherJob] = $this->otherCompany();
        $first = $this->interestedOffer($talent);
        $second = JobApplication::create(['job_posting_id' => $otherJob->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $this->offerJob($first);
        $this->offerJob($second);

        app(HiringDecision::class)->accept($first->fresh(), null);

        $this->assertThrows(fn () => app(HiringDecision::class)->accept($second->fresh(), null), HttpException::class);
        $this->assertSame('ditutup', $second->fresh()->status);
    }

    public function test_hiring_company_gets_no_closed_email_for_its_other_process(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        $hrd = $this->hrd();
        $offer = $this->interestedOffer($talent);
        $application = JobApplication::create(['job_posting_id' => $hrd->company->jobPostings()->open()->firstOrFail()->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $this->offerJob($offer);

        app(HiringDecision::class)->accept($offer->fresh(), null);

        $this->assertSame('ditutup', $application->fresh()->status);
        Notification::assertSentTo($hrd, JobOfferAnswered::class);
        Notification::assertNotSentTo($hrd, ProcessClosed::class);
    }

    public function test_expired_job_offer_cannot_be_taken(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        $offer = $this->interestedOffer($talent);
        $this->offerJob($offer);
        $offer->update(['job_offer_expires_at' => now()->subHour()]);

        $this->assertThrows(fn () => app(HiringDecision::class)->accept($offer->fresh(), null), HttpException::class);
        $this->assertSame('tawaran_kerja', $offer->fresh()->status);
    }

    public function test_job_offer_gets_one_reminder_then_expires(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $offer = $this->interestedOffer($talent);
        $this->offerJob($offer);

        $this->travel(4)->days(); // 3 days left: too early
        $this->artisan('kabelota:job-offers')->assertSuccessful();
        Notification::assertNotSentTo($user, JobOfferReminder::class);

        $this->travel(1)->days(); // 2 days left
        $this->artisan('kabelota:job-offers');
        $this->artisan('kabelota:job-offers');
        Notification::assertSentToTimes($user, JobOfferReminder::class, 1);

        $this->travel(3)->days(); // past the deadline
        $this->artisan('kabelota:job-offers');
        $this->assertSame('kedaluwarsa', $offer->fresh()->status);
        Notification::assertSentTo($this->hrd(), JobOfferExpired::class);

        $this->offerJob($offer->fresh()); // the company may offer again
        $this->assertSame('tawaran_kerja', $offer->fresh()->status);
    }

    public function test_user_text_in_emails_cannot_inject_links(): void
    {
        $html = (new ProcessClosed('Budi', ['Site Engineer'], '[klik](http://evil.test)'))
            ->toMail(User::factory()->create())->render();

        $this->assertStringNotContainsString('<a href="http://evil.test"', (string) $html);

        $html = (string) (new ProcessClosed('Budi', ['Site Engineer'], 'Gaji Rp5.000.000, mulai 1 Nov. gaji > 5jt'))
            ->toMail(User::factory()->create())->render();

        $this->assertStringContainsString('Rp5.000.000', $html);
        $this->assertStringContainsString('1 Nov.', $html);
        $this->assertStringNotContainsString('\.', $html);
        $this->assertStringNotContainsString('&amp;gt;', $html);
    }

    public function test_company_offers_and_talent_takes_job_through_the_pages(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $offer = $this->interestedOffer($talent);
        $start = today()->addWeek()->toDateString();

        $this->actingAs($this->hrd())->post(route('company.job-offer.store', ['rekrut', $offer->id]), ['job_offer_position' => 'Site Engineer', 'job_offer_start_date' => $start])
            ->assertRedirect()->assertSessionHas('status');

        $this->actingAs($user)->get('/tawaran')->assertOk()->assertSee('Tawaran kerja')->assertSee('Ya, ambil tawaran ini');
        $this->post(route('talent.job-offer.accept', ['rekrut', $offer->id]), ['farewell' => 'Terima kasih.'])->assertRedirect(route('talent.offers'));
        $this->assertSame('diambil', $offer->fresh()->status);
    }

    public function test_job_offer_rules(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $hrd = $this->hrd();
        $offer = $this->interestedOffer($talent);
        $url = route('company.job-offer.store', ['rekrut', $offer->id]);

        // Position and start date are required, and the start date cannot be in the past.
        $this->actingAs($hrd)->post($url, [])->assertSessionHasErrors(['job_offer_position', 'job_offer_start_date']);
        $this->post($url, ['job_offer_position' => 'X', 'job_offer_start_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('job_offer_start_date');

        // Another company cannot offer a job on this process.
        [$otherUser] = $this->otherCompany();
        $this->actingAs($otherUser)->post($url, ['job_offer_position' => 'X', 'job_offer_start_date' => today()->addDay()->toDateString()])->assertForbidden();

        // Another talent cannot answer it.
        $this->offerJob($offer);
        $this->actingAs(User::factory()->create(['role' => 'talenta']))->post(route('talent.job-offer.accept', ['rekrut', $offer->id]))->assertForbidden();

        // After the talent takes it, the company's other process with this talent is locked.
        $application = JobApplication::create(['job_posting_id' => $hrd->company->jobPostings()->open()->firstOrFail()->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $this->actingAs($user)->post(route('talent.job-offer.accept', ['rekrut', $offer->id]));
        $this->assertSame('ditutup', $application->fresh()->status);
        $this->actingAs($hrd)->post(route('company.applications.update', $application), ['status' => 'ditinjau'])->assertStatus(422);
        $this->post(route('company.job-offer.store', ['lamaran', $application->id]), ['job_offer_position' => 'X', 'job_offer_start_date' => today()->addDay()->toDateString()])->assertStatus(422);
    }
}
