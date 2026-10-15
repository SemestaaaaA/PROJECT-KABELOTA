# Portal Perusahaan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Dashboard bento untuk perusahaan di `/perusahaan` dan halaman pelamar dengan filter status, tombol aksi, dan modal Tolak beralasan.

**Architecture:** Data dashboard dihitung di `App\Support\CompanyDashboardData` (satu kelas, satu tanggung jawab), controller hanya merakit view. Penolakan memakai endpoint lama `company.applications.update` dengan field tambahan `reason` + `note` yang disimpan di kolom baru `job_applications.company_note`. Tab perusahaan disatukan di komponen `<x-company-tabs />`.

**Tech Stack:** Laravel 13, Blade + Alpine.js (x-trap), PHPUnit (`RefreshDatabase` + DemoSeeder; sandi akun contoh lokal/tes = `password`).

Spec: [../specs/2026-10-16-portal-perusahaan-design.md](../specs/2026-10-16-portal-perusahaan-design.md). Semua perintah dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`. Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Jalankan `npm run build` sebelum tes setiap kali Blade/CSS berubah.

Catatan Blade: jangan campur `@php(...)` sebaris dengan blok `@php … @endphp` dalam satu file; jangan tempelkan directive ke huruf (`kata@if`).

---

### Task 1: Penolakan beralasan (data + controller + email)

**Files:** Create `database/migrations/2026_10_16_000001_add_company_note_to_job_applications.php`, `tests/Feature/CompanyPortalTest.php`. Modify `app/Models/JobApplication.php`, `app/Http/Controllers/CompanyDashboardController.php` (`updateApplication`), `app/Notifications/ApplicationStatusChanged.php`, `resources/views/talent/applications.blade.php`.

- [ ] **Step 1: Tes gagal** — `tests/Feature/CompanyPortalTest.php`:

```php
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
}
```

- [ ] **Step 2:** `php artisan test --compact --filter=CompanyPortalTest` → FAIL.

- [ ] **Step 3: Migrasi** `database/migrations/2026_10_16_000001_add_company_note_to_job_applications.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The company's reason when it rejects an application; shown to the talent and sent in the email.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('company_note', 400)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', fn (Blueprint $table) => $table->dropColumn('company_note'));
    }
};
```

- [ ] **Step 4: Model** — di `app/Models/JobApplication.php`, di bawah `COMPANY_STATUSES`:

```php
    /** Reasons a company can pick when rejecting; "Lainnya" means only the free-text note is used. */
    public const REJECT_REASONS = ['Pengalaman belum sesuai', 'Jenjang SKK belum sesuai', 'Posisi sudah terisi', 'Lokasi tidak cocok', 'Lainnya'];
```

- [ ] **Step 5: Controller** — ganti `updateApplication()` di `CompanyDashboardController`:

```php
    public function updateApplication(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->jobPosting->company_id === $request->user()->company?->id, 403);
        abort_unless($application->companyCanChangeStatus(), 422, 'Status lamaran ini sudah final.');

        $data = $request->validate([
            'status' => ['required', Rule::in(JobApplication::COMPANY_STATUSES)],
            'reason' => ['nullable', Rule::in(JobApplication::REJECT_REASONS)],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['reason' => 'alasan', 'note' => 'catatan']);

        // Only a rejection carries a note; "Lainnya" contributes nothing beyond the free text.
        $note = $data['status'] === 'ditolak'
            ? collect([($data['reason'] ?? null) === 'Lainnya' ? null : ($data['reason'] ?? null), $data['note'] ?? null])
                ->filter(fn ($part) => filled($part))->map(fn ($part) => rtrim(trim($part), '.'))->implode('. ')
            : '';
        $note = $note === '' ? null : $note.'.';

        $changed = $application->status !== $data['status'];
        $application->update(['status' => $data['status'], 'status_changed_at' => now(), 'company_note' => $note]);
        if ($changed && $data['status'] !== 'baru') {
            $application->talent->user?->notify(new \App\Notifications\ApplicationStatusChanged($application));
        }

        return back()->with('status', 'Status lamaran '.Str::before($application->talent->name, ',').' diubah ke '.$application->statusLabel().'.');
    }
```

(Hasil: "Posisi sudah terisi" + "Terima kasih sudah melamar." → `Posisi sudah terisi. Terima kasih sudah melamar.`; "Lainnya" + "Domisili terlalu jauh." → `Domisili terlalu jauh.`)

- [ ] **Step 6: Email** — di `ApplicationStatusChanged::message()`, sebelum `->action(...)` tambahkan:

```php
            ->lineIf($this->application->status === 'ditolak' && filled($this->application->company_note),
                'Catatan perusahaan: "'.self::plain($this->application->company_note).'"')
```

- [ ] **Step 7: Lamaran Saya** — di `resources/views/talent/applications.blade.php`, di rantai `@if ($app->isJobOffer()) … @elseif ($app->status === 'ditutup') … @elseif ($app->status === 'kedaluwarsa') …`, tambahkan sebelum `@endif`:

```blade
            @elseif ($app->status === 'ditolak' && $app->company_note)
                <p class="demo-note">Catatan perusahaan: "{{ $app->company_note }}"</p>
```

- [ ] **Step 8:** tes `--filter=CompanyPortalTest` → PASS; suite penuh → lulus.
- [ ] **Step 9: Commit** `feat(perusahaan): tolak lamaran dengan alasan + catatan ke talenta`.

---

### Task 2: Halaman pelamar (filter, info, tombol aksi, modal Tolak)

**Files:** Create `resources/views/components/reject-applicant.blade.php`. Modify `CompanyDashboardController::applicants`, `resources/views/company/applicants.blade.php`, `resources/css/app.css`. Test: `CompanyPortalTest`.

- [ ] **Step 1: Tes gagal**:

```php
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
```

- [ ] **Step 2:** jalankan → FAIL.

- [ ] **Step 3: Controller** — di `CompanyDashboardController` tambahkan konstanta dan ganti `applicants()`:

```php
    /** Applicant tabs: key => statuses (null = all). */
    private const APPLICANT_FILTERS = [
        'semua' => null,
        'baru' => ['baru'],
        'ditinjau' => ['ditinjau'],
        'tawaran' => ['tawaran_kerja'],
        'selesai' => ['diambil', 'ditolak', 'ditutup', 'kedaluwarsa'],
    ];

    public function applicants(Request $request, JobPosting $job): View
    {
        abort_unless($job->company_id === $request->user()->company?->id, 403);

        $filter = array_key_exists((string) $request->query('status'), self::APPLICANT_FILTERS) ? (string) $request->query('status') : 'semua';
        $perStatus = $job->applications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('company.applicants', [
            'job' => $job,
            'filter' => $filter,
            'tabs' => collect(self::APPLICANT_FILTERS)->map(fn (?array $statuses) => $statuses ? $perStatus->only($statuses)->sum() : $perStatus->sum()),
            'applications' => $job->applications()->with(['talent.primaryCertification'])
                ->when(self::APPLICANT_FILTERS[$filter], fn ($q, array $statuses) => $q->whereIn('status', $statuses))
                ->orderByRaw("case status when 'tawaran_kerja' then 0 when 'baru' then 1 when 'ditinjau' then 2 when 'diambil' then 3 else 4 end")
                ->latest()->get(),
        ]);
    }
```

- [ ] **Step 4: Komponen** `resources/views/components/reject-applicant.blade.php`:

```blade
@props(['application'])
@php($key = 'rj-'.$application->id)
<div x-data="{ open: @js(old('reject_key') === $key && $errors->any()), reason: @js(old('reject_key') === $key ? old('reason', '') : '') }" style="display:contents">
    <button type="button" class="btn btn-line btn-sm" aria-haspopup="dialog" aria-label="Tolak lamaran {{ \Illuminate\Support\Str::before($application->talent->name, ',') }}" @click="open = true"><i class="ph ph-x-circle" aria-hidden="true"></i> Tolak</button>
    <div class="modal-bg" x-show="open" x-cloak x-transition.opacity @click.self="open = false" @keydown.escape.window="open = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="{{ $key }}-h" method="post" action="{{ route('company.applications.update', $application) }}" x-trap.noscroll="open">@csrf
            <input type="hidden" name="status" value="ditolak">
            <input type="hidden" name="reject_key" value="{{ $key }}">
            <header>
                <span class="lbl">Tolak pelamar</span>
                <h2 id="{{ $key }}-h">Tolak lamaran {{ \Illuminate\Support\Str::before($application->talent->name, ',') }}?</h2>
            </header>
            <div class="content">
                <p class="demo-note">Talenta menerima email pemberitahuan. Selama belum ada tawaran kerja, Anda masih bisa meninjau ulang lamaran ini.</p>
                <fieldset class="reason-list">
                    <legend class="fld-legend">Alasan <span class="demo-note">(opsional, ikut di email)</span></legend>
                    @foreach (\App\Models\JobApplication::REJECT_REASONS as $i => $reason)
                        <label class="reason-opt"><input type="radio" name="reason" value="{{ $reason }}" x-model="reason"> {{ $reason }}</label>
                    @endforeach
                    @if (old('reject_key') === $key)@error('reason')<span class="err">{{ $message }}</span>@enderror @endif
                </fieldset>
                <div class="fld"><label for="{{ $key }}-n">Catatan untuk talenta <span class="demo-note">(opsional)</span></label>
                    <textarea class="box @error('note') is-err @enderror" id="{{ $key }}-n" name="note" rows="3" maxlength="300" :placeholder="reason === 'Lainnya' ? 'Tulis alasannya di sini' : 'mis. Terima kasih sudah melamar, semoga sukses.'">{{ old('reject_key') === $key ? old('note') : '' }}</textarea>
                    @if (old('reject_key') === $key)@error('note')<span class="err">{{ $message }}</span>@enderror @endif</div>
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="open = false">Batal</button>
                <button class="btn btn-ink" type="submit">Ya, tolak pelamar</button>
            </footer>
        </form>
    </div>
</div>
```

- [ ] **Step 5: View pelamar** — `resources/views/company/applicants.blade.php`:
  1. Di bawah `.page-h`, tambahkan tab filter:

```blade
    <nav class="page-tabs" aria-label="Saring pelamar">
        @foreach (['semua' => 'Semua', 'baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'tawaran' => 'Tawaran kerja', 'selesai' => 'Selesai'] as $key => $label)
            <a href="{{ route('company.applicants', $key === 'semua' ? $job : [$job, 'status' => $key]) }}" @if ($filter === $key) aria-current="page" @endif>{{ $label }}@if ($tabs[$key])<span class="count">{{ $tabs[$key] }}</span>@endif</a>
        @endforeach
    </nav>
```

  2. Ganti `<p class="demo-note">{{ $t->headline }} · …</p>` di kepala kartu dengan baris info:

```blade
                    <p class="demo-note">{{ $t->headline }}</p>
                    <ul class="applicant-facts">
                        @if ($t->primaryCertification)
                            <li><i class="ph ph-certificate" aria-hidden="true"></i> Jenjang {{ $t->primaryCertification->jenjang }}@if (! $t->primaryCertification->isValid()) <span class="tag tag-warn">SKK kedaluwarsa</span>@endif</li>
                        @endif
                        <li><i class="ph ph-hard-hat" aria-hidden="true"></i> {{ $t->isAlumni() ? ($t->experienceYears() ?? 0).' thn pengalaman' : 'Semester '.$t->semester }}</li>
                        <li><i class="ph ph-map-pin" aria-hidden="true"></i> {{ $t->city }}</li>
                        <li><i class="ph ph-clock" aria-hidden="true"></i> melamar {{ $app->created_at->diffForHumans() }}</li>
                    </ul>
```

  3. Ganti blok form dropdown `@if ($app->companyCanChangeStatus()) <form …status-form…> … </form> @endif` dengan tombol aksi:

```blade
                @if ($app->status === 'baru')
                    <form method="post" action="{{ route('company.applications.update', $app) }}">@csrf
                        <input type="hidden" name="status" value="ditinjau">
                        <button class="btn btn-line btn-sm" type="submit"><i class="ph ph-eye" aria-hidden="true"></i> Tandai ditinjau</button>
                    </form>
                @endif
                @if (in_array($app->status, ['baru', 'ditinjau'], true))
                    <x-reject-applicant :application="$app" />
                @endif
                @if ($app->status === 'ditolak' && $app->companyCanChangeStatus())
                    <form method="post" action="{{ route('company.applications.update', $app) }}">@csrf
                        <input type="hidden" name="status" value="ditinjau">
                        <button class="btn btn-line btn-sm" type="submit"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i> Tinjau ulang</button>
                    </form>
                @endif
```

  (Biarkan `@if ($app->canOfferJob()) <x-job-offer-form …>` dan blok kontak apa adanya.)

  4. Di rantai catatan di bawah kartu, tambahkan sebelum `@endif`:

```blade
            @elseif ($app->status === 'ditolak' && $app->company_note)
                <p class="demo-note">Alasan Anda: "{{ $app->company_note }}"</p>
```

  5. Ubah empty state supaya membedakan filter: kalau `$filter !== 'semua'` tampilkan "Tidak ada pelamar di tab ini." + tautan "Lihat semua pelamar".

- [ ] **Step 6: CSS** — tambahkan di akhir `resources/css/app.css`:

```css
/* Applicants page: quick facts and the reject reasons. */
.applicant-facts { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 13.5px; color: var(--muted); }
.applicant-facts li { display: inline-flex; align-items: center; gap: 5px; }
.reason-list { border: 0; margin: 0; padding: 0; display: grid; gap: 8px; }
.reason-opt { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: 1px solid var(--line-strong); border-radius: var(--r); cursor: pointer; font-size: 14.5px; }
.reason-opt:has(input:checked) { border-color: var(--orange); background: var(--orange-soft); }
.reason-opt:focus-within { outline: 2px solid var(--yellow); outline-offset: 2px; }
```

- [ ] **Step 7:** `npm run build`, tes → PASS, suite penuh lulus (tes lama di `KabelotaFlowTest`/`HiringDecisionTest` yang membuka halaman pelamar harus tetap lulus).
- [ ] **Step 8: Commit** `feat(perusahaan): halaman pelamar dengan filter, tombol aksi, dan konfirmasi tolak`.

---

### Task 3: Dashboard bento `/perusahaan`

**Files:** Create `app/Support/CompanyDashboardData.php`, `resources/views/company/dashboard.blade.php`, `resources/views/components/company-tabs.blade.php`. Modify `routes/web.php`, `CompanyDashboardController` (method `dashboard`), `app/Http/Controllers/Auth/AuthController.php` (`homeFor`), `resources/views/company/jobs.blade.php`, `company/offers.blade.php`, `company/profile.blade.php` (tab), `resources/views/components/layouts/app.blade.php` (menu akun desktop + HP), `resources/css/app.css`. Test: `CompanyPortalTest`.

- [ ] **Step 1: Tes gagal**:

```php
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
```

(Cek dulu bagaimana middleware `role:perusahaan` menolak peran lain: `KabelotaFlowTest` baris ~350 mengharapkan redirect ke `/`. Sesuaikan asersi kalau perilakunya berbeda.)

- [ ] **Step 2:** jalankan → FAIL.

- [ ] **Step 3: Data** `app/Support/CompanyDashboardData.php`:

```php
<?php

namespace App\Support;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Everything the company dashboard shows, computed for one company. */
class CompanyDashboardData
{
    public function __construct(public Company $company) {}

    /** @return array{items: list<array{icon: string, text: string, url: string}>, total: int} */
    public function todo(int $limit = 6): array
    {
        $items = [];

        $newByJob = $this->applications()->where('status', 'baru')->with('jobPosting')->get()->groupBy('job_posting_id');
        foreach ($newByJob as $apps) {
            $job = $apps->first()->jobPosting;
            $items[] = ['icon' => 'ph-user-plus', 'text' => $apps->count().' pelamar baru di '.$job->title, 'url' => route('company.applicants', [$job, 'status' => 'baru'])];
        }

        foreach ($this->company->offers()->where('status', 'diterima')->with('talent')->latest('responded_at')->get() as $offer) {
            $items[] = ['icon' => 'ph-hand-heart', 'text' => Str::before($offer->talent->name, ',').' tertarik dengan tawaran '.$offer->position.'. Kirim tawaran kerja?', 'url' => route('company.offers')];
        }

        $expired = $this->company->offers()->where('status', 'kedaluwarsa')->with('talent')->get()
            ->concat($this->applications()->where('status', 'kedaluwarsa')->with('talent')->get());
        foreach ($expired as $record) {
            $items[] = ['icon' => 'ph-hourglass-low', 'text' => 'Tawaran kerja untuk '.Str::before($record->talent->name, ',').' kedaluwarsa. Kirim ulang?', 'url' => $record->companyInboxUrl()];
        }

        return ['items' => array_slice($items, 0, $limit), 'total' => count($items)];
    }

    /** @return array{pending: int, nearest: ?\Illuminate\Support\Carbon, taken: int} */
    public function jobOffers(): array
    {
        $offers = $this->company->offers()->where('status', 'tawaran_kerja');
        $apps = $this->applications()->where('status', 'tawaran_kerja');
        $nearest = collect([(clone $offers)->min('job_offer_expires_at'), (clone $apps)->min('job_offer_expires_at')])->filter()->min();

        return [
            'pending' => (clone $offers)->count() + (clone $apps)->count(),
            'nearest' => $nearest ? \Illuminate\Support\Carbon::parse($nearest) : null,
            'taken' => $this->company->offers()->where('status', 'diambil')->count() + $this->applications()->where('status', 'diambil')->count(),
        ];
    }

    /** @return array{live: int, applicants: int, closingSoon: ?\App\Models\JobPosting} */
    public function jobs(): array
    {
        return [
            'live' => $this->company->jobPostings()->open()->count(),
            'applicants' => $this->applications()->whereHas('jobPosting', fn (Builder $q) => $q->open())->count(),
            'closingSoon' => $this->company->jobPostings()->open()->orderBy('closes_at')->first(),
        ];
    }

    public function openContacts(): int
    {
        return $this->company->offers()->whereIn('status', RecruitmentOffer::CONTACT_STATUSES)->count()
            + $this->applications()->whereIn('status', JobApplication::CONTACT_STATUSES)->count();
    }

    /** @return array{verified: bool, label: string, percent: int, missing: list<string>} */
    public function profile(): array
    {
        $c = $this->company;
        $checks = ['Logo' => filled($c->logo_path), 'Tentang perusahaan' => filled($c->about), 'Website' => filled($c->website), 'NIB / SBU' => filled($c->legal_doc_path) || filled($c->nib)];

        return [
            'verified' => $c->isVerified(),
            'label' => $c->statusLabel(),
            'percent' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn (bool $done) => ! $done)),
        ];
    }

    private function applications(): Builder
    {
        return JobApplication::query()->whereHas('jobPosting', fn (Builder $q) => $q->where('company_id', $this->company->id));
    }
}
```

- [ ] **Step 4: Rute + controller**:
  - `routes/web.php`, grup perusahaan, baris pertama: `Route::get('/perusahaan', [CompanyDashboardController::class, 'dashboard'])->name('company.dashboard');`
  - `CompanyDashboardController`:

```php
    public function dashboard(Request $request): View
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        return view('company.dashboard', ['company' => $company, 'd' => new \App\Support\CompanyDashboardData($company)]);
    }
```

  - `AuthController::homeFor`: `'perusahaan' => $user->isVerifiedCompany() ? route('company.dashboard') : route('company.profile'),`

- [ ] **Step 5: Komponen tab** `resources/views/components/company-tabs.blade.php`:

```blade
<x-page-tabs :items="[['Dashboard', 'company.dashboard'], ['Lowongan Saya', 'company.jobs'], ['Tawaran Terkirim', 'company.offers'], ['Profil Perusahaan', 'company.profile']]" />
```

Ganti tiga baris `<x-page-tabs :items="[['Lowongan Saya', 'company.jobs'], …]" />` di `company/jobs.blade.php`, `company/offers.blade.php`, `company/profile.blade.php` dengan `<x-company-tabs />`.

- [ ] **Step 6: Menu akun** — di `layouts/app.blade.php`, di cabang `@elseif ($me->isCompany())` menu desktop (role="menuitem") dan menu HP, tambahkan sebagai tautan pertama:
  - desktop: `<a role="menuitem" href="{{ route('company.dashboard') }}"><i class="ph ph-squares-four" aria-hidden="true"></i> Dashboard</a>`
  - HP: `<a href="{{ route('company.dashboard') }}"><i class="ph ph-squares-four" aria-hidden="true"></i> Dashboard</a>`

- [ ] **Step 7: View** `resources/views/company/dashboard.blade.php`:

```blade
<x-layouts.app title="Dashboard Perusahaan · Kabelota">
@php
    $todo = $d->todo();
    $offers = $d->jobOffers();
    $jobs = $d->jobs();
    $profile = $d->profile();
@endphp
<div class="wrap" style="max-width:1100px">
    <div class="page-h" style="display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap">
        <div><h1>{{ $company->name }}</h1><p>Ringkasan rekrutmen Anda hari ini.</p></div>
        <x-post-job-button class="btn btn-accent" />
    </div>
    <x-company-tabs />

    <div class="bento">
        <section class="bento-card wide" aria-labelledby="b-todo">
            <div class="bento-head"><h2 id="b-todo" class="lbl">Perlu tindakan</h2>@if ($todo['total'])<span class="count">{{ $todo['total'] }}</span>@endif</div>
            @if ($todo['items'])
                <ul class="todo-list">
                    @foreach ($todo['items'] as $item)
                        <li><a href="{{ $item['url'] }}"><i class="ph {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['text'] }}</span><i class="ph ph-caret-right" aria-hidden="true"></i></a></li>
                    @endforeach
                </ul>
                @if ($todo['total'] > count($todo['items']))<p class="demo-note">+{{ $todo['total'] - count($todo['items']) }} lainnya di halaman pelamar dan tawaran.</p>@endif
            @else
                <div class="bento-empty"><i class="ph ph-check-circle" aria-hidden="true"></i><b>Semua beres</b><span>Tidak ada pelamar atau tawaran yang menunggu Anda.</span></div>
            @endif
        </section>

        <section class="bento-card" aria-labelledby="b-offers">
            <h2 id="b-offers" class="lbl">Tawaran kerja</h2>
            <p class="bento-num">{{ $offers['pending'] }}</p>
            <p class="demo-note">{{ $offers['pending'] ? 'menunggu jawaban talenta'.($offers['nearest'] ? ', batas terdekat '.$offers['nearest']->translatedFormat('j M') : '') : 'tidak ada yang menunggu jawaban' }}</p>
            <p class="bento-foot"><i class="ph ph-handshake" aria-hidden="true"></i> {{ $offers['taken'] }} diambil talenta</p>
        </section>

        <section class="bento-card" aria-labelledby="b-jobs">
            <h2 id="b-jobs" class="lbl">Lowongan aktif</h2>
            <p class="bento-num">{{ $jobs['live'] }}</p>
            <p class="demo-note">{{ $jobs['applicants'] }} pelamar di lowongan yang tayang</p>
            @if ($jobs['closingSoon'])<p class="bento-foot"><i class="ph ph-calendar" aria-hidden="true"></i> {{ \Illuminate\Support\Str::limit($jobs['closingSoon']->title, 32) }} tutup {{ $jobs['closingSoon']->closes_at->translatedFormat('j M') }}</p>@endif
            <a class="textlink" href="{{ route('company.jobs') }}">Lihat lowongan</a>
        </section>

        <section class="bento-card" aria-labelledby="b-contacts">
            <h2 id="b-contacts" class="lbl">Kontak terbuka</h2>
            <p class="bento-num">{{ $d->openContacts() }}</p>
            <p class="demo-note">talenta yang nomor HP dan emailnya bisa Anda lihat</p>
            <a class="textlink" href="{{ route('company.offers') }}">Lihat tawaran terkirim</a>
        </section>

        <section @class(['bento-card', 'alert' => ! $profile['verified']]) aria-labelledby="b-profile">
            <h2 id="b-profile" class="lbl">Profil perusahaan</h2>
            <p><span class="badge {{ $profile['verified'] ? 'b-ok' : 'b-offer' }}">{{ $profile['label'] }}</span></p>
            <div class="meter-bar" role="img" aria-label="Kelengkapan profil {{ $profile['percent'] }} persen"><span style="width:{{ $profile['percent'] }}%"></span></div>
            <p class="demo-note">{{ $profile['percent'] }}% lengkap{{ $profile['missing'] ? '. Belum ada: '.implode(', ', $profile['missing']) : '' }}</p>
            <a class="textlink" href="{{ route('company.profile') }}">{{ $profile['percent'] < 100 || ! $profile['verified'] ? 'Lengkapi profil' : 'Lihat profil' }}</a>
        </section>
    </div>
</div>
</x-layouts.app>
```

(File ini memakai blok `@php … @endphp`; jangan ada `@php(...)` sebaris di file yang sama.)

- [ ] **Step 8: CSS** — tambahkan di akhir `resources/css/app.css`:

```css
/* Company dashboard (bento). */
.bento { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: minmax(160px, auto); gap: 14px; margin: 20px 0 40px; }
.bento-card { background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--r-lg); padding: 18px; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.bento-card.wide { grid-column: span 2; grid-row: span 2; }
.bento-card.alert { border-color: var(--orange); box-shadow: 4px 4px 0 var(--orange); }
.bento-head { display: flex; align-items: center; gap: 8px; }
.bento-num { font: 800 46px/1 var(--f-display); }
.bento-foot { margin-top: auto; font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.bento-empty { margin: auto; display: grid; justify-items: center; gap: 6px; text-align: center; color: var(--muted); }
.bento-empty .ph { font-size: 40px; color: var(--ok); }
.bento-empty b { color: var(--ink); font-size: 17px; }
.todo-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.todo-list a { display: flex; align-items: center; gap: 10px; padding: 11px 12px; border: 1px solid var(--line); border-radius: var(--r); text-decoration: none; font-size: 14.5px; }
.todo-list a > .ph:first-child { font-size: 20px; color: var(--orange); flex: none; }
.todo-list a > span { flex: 1; min-width: 0; }
.todo-list a > .ph:last-child { color: var(--muted); }
.meter-bar { height: 8px; background: var(--line); border-radius: 99px; overflow: hidden; }
.meter-bar span { display: block; height: 100%; background: linear-gradient(90deg, #F5B800 55%, var(--orange)); }
@media (hover: hover) { .todo-list a { transition: border-color .15s, transform .15s; } .todo-list a:hover { border-color: var(--orange); transform: translateX(2px); } }
@media (max-width: 900px) { .bento { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .bento { grid-template-columns: 1fr; } .bento-card.wide { grid-column: auto; grid-row: auto; } }
```

- [ ] **Step 9:** `npm run build`, tes `--filter=CompanyPortalTest` → PASS; suite penuh → lulus.
- [ ] **Step 10: Commit** `feat(perusahaan): dashboard bento + tab Dashboard + tujuan login`.
