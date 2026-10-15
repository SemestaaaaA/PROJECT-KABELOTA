<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyPortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hrd(): User
    {
        return User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
    }

    /** @return array{0: User, 1: Talent} */
    private function talent(string $name = 'Pelamar Uji'): array
    {
        $user = User::factory()->create(['role' => 'talenta']);
        $talent = Talent::factory()->create(['user_id' => $user->id, 'name' => $name, 'availability' => Availability::Tersedia]);

        return [$user, $talent];
    }

    private function applyTo(JobPosting $job, Talent $talent, string $status = 'baru'): JobApplication
    {
        return JobApplication::create(['job_posting_id' => $job->id, 'talent_id' => $talent->id, 'status' => $status]);
    }

    private function hrdJob(): JobPosting
    {
        return $this->hrd()->company->jobPostings()->open()->firstOrFail();
    }

    public function test_rejecting_with_a_reason_stores_and_sends_it(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $application = $this->applyTo($this->hrdJob(), $talent);

        $this->actingAs($this->hrd())->post(route('company.applications.update', $application), [
            'status' => 'ditolak', 'reason' => 'Posisi sudah terisi', 'note' => 'Terima kasih sudah melamar.',
        ])->assertRedirect();

        $this->assertSame('Posisi sudah terisi. Terima kasih sudah melamar.', $application->fresh()->company_note);
        Notification::assertSentTo($user, ApplicationStatusChanged::class, function (ApplicationStatusChanged $n) use ($user) {
            return str_contains($n->toMail($user)->render()->toHtml(), 'Posisi sudah terisi');
        });
        $this->actingAs($user)->get('/lamaran')->assertSee('Catatan perusahaan')->assertSee('Posisi sudah terisi');
    }

    public function test_reject_reason_must_come_from_the_list_and_other_statuses_clear_the_note(): void
    {
        Notification::fake();
        [, $talent] = $this->talent();
        $application = $this->applyTo($this->hrdJob(), $talent);

        $this->actingAs($this->hrd())->post(route('company.applications.update', $application), ['status' => 'ditolak', 'reason' => 'Asal-asalan'])
            ->assertSessionHasErrors('reason');

        $this->post(route('company.applications.update', $application), ['status' => 'ditolak', 'reason' => 'Lainnya', 'note' => 'Domisili terlalu jauh.']);
        $this->assertSame('Domisili terlalu jauh.', $application->fresh()->company_note);

        $this->post(route('company.applications.update', $application), ['status' => 'ditinjau']);
        $this->assertNull($application->fresh()->company_note);
    }

    public function test_applicants_page_filters_and_shows_actions_per_status(): void
    {
        [, $new] = $this->talent('Pelamar Baru Satu');
        [, $reviewed] = $this->talent('Pelamar Ditinjau Dua');
        $job = $this->hrdJob();
        $this->applyTo($job, $new, 'baru');
        $this->applyTo($job, $reviewed, 'ditinjau');

        $this->actingAs($this->hrd());
        $all = $this->get(route('company.applicants', $job))->assertOk()
            ->assertSee('Pelamar Baru Satu')->assertSee('Pelamar Ditinjau Dua')
            ->assertSee('Tandai ditinjau')->assertSee('Ya, tolak pelamar')->assertSee('Tawarkan kerja')
            ->assertDontSee('<select class="box" id="st-', false);

        $this->get(route('company.applicants', [$job, 'status' => 'baru']))->assertOk()
            ->assertSee('Pelamar Baru Satu')->assertDontSee('Pelamar Ditinjau Dua');
        $this->get(route('company.applicants', [$job, 'status' => 'tidak-ada']))->assertOk()->assertSee('Pelamar Ditinjau Dua');
    }

    public function test_verified_company_lands_on_the_dashboard(): void
    {
        $this->post('/masuk', ['email' => 'hrd@demo.kabelota.test', 'password' => 'password'])->assertRedirect(route('company.dashboard'));
    }

    public function test_dashboard_lists_what_needs_attention_for_this_company_only(): void
    {
        [, $new] = $this->talent('Rahmat Pelamar');
        [, $interested] = $this->talent('Dewi Tertarik');
        [, $stranger] = $this->talent('Orang Perusahaan Lain');
        $hrd = $this->hrd();
        $job = $this->hrdJob();
        $this->applyTo($job, $new, 'baru');
        RecruitmentOffer::create([
            'talent_id' => $interested->id, 'company_id' => $hrd->company->id, 'company_name' => $hrd->company->name,
            'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id', 'position' => 'Drafter Uji', 'message' => 'Pesan tawaran uji coba yang cukup panjang.',
            'status' => 'diterima', 'responded_at' => now(),
        ]);
        $otherJob = JobPosting::open()->where('company_id', '!=', $hrd->company->id)->firstOrFail();
        $this->applyTo($otherJob, $stranger, 'baru');

        $this->actingAs($hrd)->get(route('company.dashboard'))->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('pelamar baru di')->assertSee(e($job->title), false)
            ->assertSee('Dewi Tertarik')->assertSee('Drafter Uji')
            ->assertSee('Lowongan aktif')->assertSee('Tawaran kerja')->assertSee('Kontak terbuka')->assertSee('Profil perusahaan')
            ->assertDontSee('Orang Perusahaan Lain');
    }

    public function test_talent_cannot_open_the_company_dashboard(): void
    {
        [$user] = $this->talent();
        $this->actingAs($user)->get(route('company.dashboard'))->assertRedirect('/');
    }
}
