# Alur Keputusan Talenta Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perusahaan bisa mengirim *tawaran kerja final*; talenta mengambil satu tawaran, lalu semua proses lain miliknya ditutup otomatis, perusahaan lain diberi tahu (dengan pesan perpisahan opsional), dan tawaran kerja kedaluwarsa setelah 7 hari.

**Architecture:** Status baru (`tawaran_kerja`, `diambil`, `ditutup`, `kedaluwarsa`) ditambahkan ke dua tabel lama (`recruitment_offers`, `job_applications`) lewat satu migrasi. Trait `HasJobOffer` memberi kedua model API yang sama; layanan `App\Services\HiringDecision` memegang semua aturan (tawarkan, ambil, tolak, ingatkan, kedaluwarsa) dalam transaksi. Controller hanya memeriksa kepemilikan dan validasi, lalu memanggil layanan.

**Tech Stack:** Laravel 13, PHP 8.4, Blade + Alpine.js (`x-trap` sudah terpasang), notifikasi email antrean (`App\Notifications\KabelotaMail`), PHPUnit dengan `RefreshDatabase` + `DemoSeeder` (`protected bool $seed = true`).

Spec: [docs/superpowers/specs/2026-10-15-alur-keputusan-talenta-design.md](../specs/2026-10-15-alur-keputusan-talenta-design.md)

Semua perintah dijalankan dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`.

---

## File map

| File | Tanggung jawab |
|---|---|
| Create `database/migrations/2026_10_15_000001_add_job_offer_columns.php` | Kolom tawaran kerja di dua tabel + konversi lamaran lama `diterima` |
| Create `app/Models/Concerns/HasJobOffer.php` | API bersama: label, badge, scope `open`, `canOfferJob`, `contactVisible`, sisa hari, nama/URL perusahaan |
| Modify `app/Models/RecruitmentOffer.php`, `app/Models/JobApplication.php` | Konstanta status per model, implementasi bagian khusus trait |
| Create `app/Services/HiringDecision.php` | `find`, `openFor`, `offerJob`, `accept`, `decline`, `remindDue`, `expireDue` |
| Create `app/Notifications/JobOfferReceived.php`, `JobOfferAnswered.php`, `ProcessClosed.php`, `JobOfferReminder.php`, `JobOfferExpired.php` | Email |
| Modify `routes/console.php` | Perintah `kabelota:job-offers` + jadwal |
| Modify `routes/web.php`, `app/Http/Controllers/TalentInboxController.php`, `app/Http/Controllers/CompanyDashboardController.php`, `app/Models/User.php` | Rute + aksi + hitungan badge |
| Create `resources/views/components/job-offer-panel.blade.php`, `resources/views/components/job-offer-form.blade.php` | UI talenta (Ambil/Tolak + modal) dan UI perusahaan (Tawarkan kerja + modal) |
| Modify `resources/views/talent/offers.blade.php`, `talent/applications.blade.php`, `company/applicants.blade.php`, `company/offers.blade.php`, `resources/css/app.css` | Pakai komponen, status baru, kontak via `contactVisible()` |
| Modify Filament: `app/Filament/Resources/RecruitmentOffers/RecruitmentOfferResource.php`, `app/Filament/Resources/JobApplications/JobApplicationResource.php`, `app/Filament/Widgets/RecruitmentFunnelChart.php`, `KabelotaStats.php`, `TopCompaniesTable.php` | Label/warna status baru, hitungan yang benar |
| Modify `app/Notifications/OfferAnswered.php`, `ApplicationStatusChanged.php` | Teks "Tertarik", hapus cabang `diterima` lamaran |
| Create `tests/Feature/HiringDecisionTest.php`; Modify `tests/Feature/KabelotaFlowTest.php` | Tes baru + penyesuaian tes lama |

Catatan: `Model::preventLazyLoading` aktif di luar produksi. Setiap query yang mengembalikan lebih dari satu record harus memakai `with(...)` untuk relasi yang dipakai (sudah ditulis di kode di bawah).

---

### Task 1: Migrasi, trait `HasJobOffer`, dan konstanta status

**Files:**
- Create: `database/migrations/2026_10_15_000001_add_job_offer_columns.php`
- Create: `app/Models/Concerns/HasJobOffer.php`
- Modify: `app/Models/RecruitmentOffer.php`, `app/Models/JobApplication.php`
- Test: `tests/Feature/HiringDecisionTest.php`

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/HiringDecisionTest.php`:

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
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: FAIL (`Call to undefined method ... canOfferJob()` dan file migrasi belum ada).

- [ ] **Step 3: Buat migrasi**

`database/migrations/2026_10_15_000001_add_job_offer_columns.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Final job offers: a company offers a position, the talent takes one, every other open process closes.
return new class extends Migration
{
    private const TABLES = ['recruitment_offers', 'job_applications'];

    private const COLUMNS = [
        'job_offer_position', 'job_offer_start_date', 'job_offer_note', 'job_offered_at',
        'job_offer_expires_at', 'job_offer_reminded_at', 'closed_at', 'closed_note',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('job_offer_position')->nullable();
                $table->date('job_offer_start_date')->nullable();
                $table->text('job_offer_note')->nullable();
                $table->timestamp('job_offered_at')->nullable();
                $table->timestamp('job_offer_expires_at')->nullable();
                $table->timestamp('job_offer_reminded_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('closed_note')->nullable();
            });
        }

        $this->convertAcceptedApplications();
    }

    /** Applications a company marked "Diterima" before this change now wait for the talent's answer. */
    public function convertAcceptedApplications(): void
    {
        DB::table('job_applications')->where('status', 'diterima')->orderBy('id')->each(function (object $row) {
            DB::table('job_applications')->where('id', $row->id)->update([
                'status' => 'tawaran_kerja',
                'job_offer_position' => DB::table('job_postings')->where('id', $row->job_posting_id)->value('title'),
                'job_offered_at' => now(),
                'job_offer_expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            DB::table($name)->whereIn('status', ['tawaran_kerja', 'diambil'])->update(['status' => 'diterima']);
            DB::table($name)->whereIn('status', ['ditutup', 'kedaluwarsa'])->update(['status' => 'ditolak']);
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
        }
    }
};
```

- [ ] **Step 4: Buat trait**

`app/Models/Concerns/HasJobOffer.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared final-job-offer behaviour for RecruitmentOffer and JobApplication.
 * The model defines STATUSES, OPEN_STATUSES, OFFERABLE_FROM, CONTACT_STATUSES, ANSWERED_AT and BADGES.
 */
trait HasJobOffer
{
    public function initializeHasJobOffer(): void
    {
        $this->mergeCasts([
            'job_offer_start_date' => 'date',
            'job_offered_at' => 'datetime',
            'job_offer_expires_at' => 'datetime',
            'job_offer_reminded_at' => 'datetime',
            'closed_at' => 'datetime',
        ]);
    }

    /** Processes that still need someone to act. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', static::OPEN_STATUSES);
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return (static::BADGES + [
            'tawaran_kerja' => 'b-offer', 'diambil' => 'b-ok', 'ditolak' => 'b-bad', 'ditutup' => 'b-off', 'kedaluwarsa' => 'b-off',
        ])[$this->status] ?? 'b-plain';
    }

    public function isJobOffer(): bool
    {
        return $this->status === 'tawaran_kerja';
    }

    public function canOfferJob(): bool
    {
        return in_array($this->status, static::OFFERABLE_FROM, true);
    }

    public function contactVisible(): bool
    {
        return in_array($this->status, static::CONTACT_STATUSES, true);
    }

    public function jobOfferDaysLeft(): int
    {
        return $this->job_offer_expires_at
            ? max(0, (int) ceil(now()->diffInHours($this->job_offer_expires_at, false) / 24))
            : 0;
    }

    abstract public function typeKey(): string;

    abstract public function title(): string;

    abstract public function companyId(): ?int;

    abstract public function companyName(): string;

    abstract public function companyUser(): ?User;

    abstract public function companyInboxUrl(): string;

    abstract public function talentInboxRoute(): string;
}
```

- [ ] **Step 5: Perbarui `app/Models/RecruitmentOffer.php`** (ganti seluruh isi)

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasJobOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    use HasJobOffer;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'responded_at' => 'datetime'];
    }

    // "diterima" keeps its database value but means "tertarik": the talent opened their contact, nothing is signed yet.
    public const STATUSES = [
        'menunggu' => 'Menunggu jawaban', 'diterima' => 'Tertarik', 'ditolak' => 'Ditolak',
        'tawaran_kerja' => 'Tawaran kerja', 'diambil' => 'Diambil', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa',
    ];

    public const OPEN_STATUSES = ['menunggu', 'diterima', 'tawaran_kerja'];

    public const OFFERABLE_FROM = ['diterima', 'kedaluwarsa'];

    public const CONTACT_STATUSES = ['diterima', 'tawaran_kerja', 'diambil'];

    public const ANSWERED_AT = 'responded_at';

    protected const BADGES = ['menunggu' => 'b-contract', 'diterima' => 'b-ok'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function typeKey(): string
    {
        return 'rekrut';
    }

    public function title(): string
    {
        return $this->position;
    }

    public function companyId(): ?int
    {
        return $this->company_id;
    }

    public function companyName(): string
    {
        return $this->company_name;
    }

    public function companyUser(): ?User
    {
        return $this->company?->user;
    }

    public function companyInboxUrl(): string
    {
        return route('company.offers');
    }

    public function talentInboxRoute(): string
    {
        return 'talent.offers';
    }
}
```

- [ ] **Step 6: Perbarui `app/Models/JobApplication.php`** (ganti seluruh isi)

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasJobOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use HasJobOffer;

    public const STATUSES = [
        'baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditolak' => 'Ditolak',
        'tawaran_kerja' => 'Tawaran kerja', 'diambil' => 'Diambil', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa',
    ];

    /** Statuses a company may set with the dropdown. Anything else goes through HiringDecision. */
    public const COMPANY_STATUSES = ['baru', 'ditinjau', 'ditolak'];

    public const OPEN_STATUSES = ['baru', 'ditinjau', 'tawaran_kerja'];

    public const OFFERABLE_FROM = ['ditinjau', 'kedaluwarsa'];

    public const CONTACT_STATUSES = ['tawaran_kerja', 'diambil'];

    public const ANSWERED_AT = 'status_changed_at';

    protected const BADGES = ['baru' => 'b-new', 'ditinjau' => 'b-contract'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function typeKey(): string
    {
        return 'lamaran';
    }

    public function title(): string
    {
        return $this->jobPosting?->title ?? '-';
    }

    public function companyId(): ?int
    {
        return $this->jobPosting?->company_id;
    }

    public function companyName(): string
    {
        return $this->jobPosting?->company?->name ?? '-';
    }

    public function companyUser(): ?User
    {
        return $this->jobPosting?->company?->user;
    }

    public function companyInboxUrl(): string
    {
        return route('company.applicants', $this->job_posting_id);
    }

    public function talentInboxRoute(): string
    {
        return 'talent.applications';
    }
}
```

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_10_15_000001_add_job_offer_columns.php app/Models/Concerns/HasJobOffer.php app/Models/RecruitmentOffer.php app/Models/JobApplication.php tests/Feature/HiringDecisionTest.php
git commit -m "feat(hiring): status tawaran kerja + trait HasJobOffer + migrasi

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Layanan `HiringDecision` (tawarkan, ambil, tolak) + email

**Files:**
- Create: `app/Services/HiringDecision.php`
- Create: `app/Notifications/JobOfferReceived.php`, `JobOfferAnswered.php`, `ProcessClosed.php`, `JobOfferReminder.php`, `JobOfferExpired.php`
- Test: `tests/Feature/HiringDecisionTest.php`

- [ ] **Step 1: Tambah tes yang gagal**

Tambahkan `use` berikut di atas file tes:

```php
use App\Notifications\JobOfferAnswered;
use App\Notifications\JobOfferReceived;
use App\Notifications\ProcessClosed;
use App\Services\HiringDecision;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
```

Tambahkan helper + tes di dalam kelas:

```php
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
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: FAIL (`Class "App\Services\HiringDecision" not found`).

- [ ] **Step 3: Buat layanan**

`app/Services/HiringDecision.php`:

```php
<?php

namespace App\Services;

use App\Enums\Availability;
use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Notifications\JobOfferAnswered;
use App\Notifications\JobOfferExpired;
use App\Notifications\JobOfferReceived;
use App\Notifications\JobOfferReminder;
use App\Notifications\ProcessClosed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Final hiring step shared by recruitment offers (company first) and job applications (talent first).
 * A company offers a job; the talent takes one, and every other open process of that talent closes.
 */
class HiringDecision
{
    public const OFFER_DAYS = 7;

    public const REMIND_WHEN_DAYS_LEFT = 2;

    public static function find(string $type, int $id): RecruitmentOffer|JobApplication
    {
        return match ($type) {
            'rekrut' => RecruitmentOffer::findOrFail($id),
            'lamaran' => JobApplication::findOrFail($id),
            default => abort(404),
        };
    }

    /** Every process of this talent that is still open, with the relations the views and emails need. */
    public static function openFor(Talent $talent): Collection
    {
        return RecruitmentOffer::open()->with('company.user')->where('talent_id', $talent->id)->get()
            ->concat(JobApplication::open()->with('jobPosting.company.user')->where('talent_id', $talent->id)->get());
    }

    /** @param array{job_offer_position: string, job_offer_start_date: string, job_offer_note?: ?string} $data */
    public function offerJob(RecruitmentOffer|JobApplication $record, array $data): void
    {
        abort_unless($record->canOfferJob(), 422, 'Tawaran kerja hanya bisa dikirim setelah talenta tertarik atau lamarannya ditinjau.');

        $record->update([
            'status' => 'tawaran_kerja',
            'job_offer_position' => $data['job_offer_position'],
            'job_offer_start_date' => $data['job_offer_start_date'],
            'job_offer_note' => $data['job_offer_note'] ?? null,
            'job_offered_at' => now(),
            'job_offer_expires_at' => now()->addDays(self::OFFER_DAYS),
            'job_offer_reminded_at' => null,
        ]);

        $record->talent->user?->notify(new JobOfferReceived($record));
    }

    public function accept(RecruitmentOffer|JobApplication $record, ?string $farewell): void
    {
        [$chosen, $closed] = DB::transaction(function () use ($record, $farewell) {
            // Lock the talent row so two "Ambil" clicks are handled one after the other.
            $talent = Talent::whereKey($record->talent_id)->lockForUpdate()->firstOrFail();
            $chosen = $record->newQuery()->whereKey($record->getKey())->firstOrFail();
            abort_unless($chosen->isJobOffer() && $chosen->job_offer_expires_at?->isFuture(), 422, 'Tawaran kerja ini sudah tidak berlaku.');

            $chosen->update(['status' => 'diambil', $chosen::ANSWERED_AT => now()]);

            $closed = self::openFor($talent)->reject(fn ($other) => $other->is($chosen))->each(
                fn ($other) => $other->update(['status' => 'ditutup', 'closed_at' => now(), 'closed_note' => $farewell])
            );

            $talent->update(['availability' => Availability::TerikatKontrak]);

            return [$chosen, $closed];
        });

        $chosen->companyUser()?->notify(new JobOfferAnswered($chosen));

        // One email per company, listing every process of theirs that closed.
        $closed->groupBy(fn ($r) => $r->companyId())->each(fn (Collection $records) => $records->first()->companyUser()?->notify(
            new ProcessClosed($chosen->talent->name, $records->map->title()->unique()->values()->all(), $farewell)
        ));
    }

    public function decline(RecruitmentOffer|JobApplication $record, ?string $note): void
    {
        abort_unless($record->isJobOffer(), 422, 'Tawaran kerja ini sudah tidak berlaku.');

        $record->update(['status' => 'ditolak', $record::ANSWERED_AT => now(), 'closed_note' => $note]);
        $record->companyUser()?->notify(new JobOfferAnswered($record));
    }

    /** Remind the talent once, when two days are left. */
    public function remindDue(): int
    {
        $count = 0;
        foreach ($this->pendingQueries() as $query) {
            $query->whereNull('job_offer_reminded_at')
                ->whereBetween('job_offer_expires_at', [now(), now()->addDays(self::REMIND_WHEN_DAYS_LEFT)])
                ->lazyById()->each(function ($record) use (&$count) {
                    $record->talent->user?->notify(new JobOfferReminder($record));
                    $record->update(['job_offer_reminded_at' => now()]);
                    $count++;
                });
        }

        return $count;
    }

    public function expireDue(): int
    {
        $count = 0;
        foreach ($this->pendingQueries() as $query) {
            $query->where('job_offer_expires_at', '<', now())->lazyById()->each(function ($record) use (&$count) {
                $record->update(['status' => 'kedaluwarsa']);
                $record->companyUser()?->notify(new JobOfferExpired($record));
                $count++;
            });
        }

        return $count;
    }

    /** @return list<\Illuminate\Database\Eloquent\Builder> */
    private function pendingQueries(): array
    {
        return [
            RecruitmentOffer::with(['talent.user', 'company.user'])->where('status', 'tawaran_kerja'),
            JobApplication::with(['talent.user', 'jobPosting.company.user'])->where('status', 'tawaran_kerja'),
        ];
    }
}
```

- [ ] **Step 4: Buat tiga email**

`app/Notifications/JobOfferReceived.php`:

```php
<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class JobOfferReceived extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $start = $r->job_offer_start_date?->translatedFormat('j F Y');

        return (new MailMessage)
            ->subject("Tawaran kerja dari {$r->companyName()}: {$r->job_offer_position}")
            ->line("{$r->companyName()} menawarkan posisi **{$r->job_offer_position}**".($start ? ", mulai {$start}." : '.'))
            ->lineIf(filled($r->job_offer_note), 'Catatan perusahaan: "'.$r->job_offer_note.'"')
            ->line('Tawaran ini berlaku sampai '.$r->job_offer_expires_at->translatedFormat('j F Y').'. Kalau Anda mengambilnya, proses Anda dengan perusahaan lain ditutup otomatis.')
            ->action('Jawab Tawaran Kerja', route($r->talentInboxRoute()));
    }
}
```

`app/Notifications/JobOfferAnswered.php`:

```php
<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class JobOfferAnswered extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $name = Str::before($r->talent->name, ',');

        if ($r->status === 'diambil') {
            return (new MailMessage)
                ->subject("{$name} mengambil tawaran kerja Anda")
                ->line("{$name} menerima tawaran kerja untuk posisi **{$r->job_offer_position}**.")
                ->line('Hubungi talenta untuk langkah berikutnya. Nomor HP dan emailnya ada di Kabelota.')
                ->action('Lihat Detail', $r->companyInboxUrl());
        }

        return (new MailMessage)
            ->subject("{$name} menolak tawaran kerja Anda")
            ->line("{$name} menolak tawaran kerja untuk posisi **{$r->job_offer_position}**.")
            ->lineIf(filled($r->closed_note), 'Alasan: "'.$r->closed_note.'"')
            ->action('Cari Talenta Lain', route('talents.index'));
    }
}
```

`app/Notifications/ProcessClosed.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Plain values only: the closed processes can be a mix of offers and applications, which cannot be queued as one collection. */
class ProcessClosed extends KabelotaMail
{
    /** @param list<string> $positions */
    public function __construct(public string $talentName, public array $positions, public ?string $farewell) {}

    protected function message(object $notifiable): MailMessage
    {
        $name = Str::before($this->talentName, ',');

        return (new MailMessage)
            ->subject("{$name} sudah mengambil tawaran kerja lain")
            ->line("{$name} sudah menerima tawaran kerja dari perusahaan lain, jadi proses berikut ditutup otomatis: **".implode(', ', $this->positions).'**.')
            ->lineIf(filled($this->farewell), 'Pesan dari talenta: "'.$this->farewell.'"')
            ->line('Riwayatnya tetap tersimpan di dashboard Anda.')
            ->action('Cari Talenta Lain', route('talents.index'));
    }
}
```

Layanan juga mengimpor dua email untuk pengingat dan kedaluwarsa, jadi buat keduanya sekarang:

`app/Notifications/JobOfferReminder.php`:

```php
<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class JobOfferReminder extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;

        return (new MailMessage)
            ->subject("Tawaran kerja {$r->job_offer_position} berakhir {$r->job_offer_expires_at->translatedFormat('j F')}")
            ->line("Tawaran kerja dari {$r->companyName()} untuk posisi **{$r->job_offer_position}** belum Anda jawab.")
            ->line('Kalau tidak dijawab sampai '.$r->job_offer_expires_at->translatedFormat('j F Y').', tawaran ini kedaluwarsa.')
            ->action('Jawab Sekarang', route($r->talentInboxRoute()));
    }
}
```

`app/Notifications/JobOfferExpired.php`:

```php
<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class JobOfferExpired extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $name = Str::before($r->talent->name, ',');

        return (new MailMessage)
            ->subject("Tawaran kerja untuk {$name} kedaluwarsa")
            ->line("{$name} tidak menjawab tawaran kerja **{$r->job_offer_position}** dalam 7 hari.")
            ->line('Anda bisa mengirim tawaran kerja lagi dari halaman yang sama kalau masih berminat.')
            ->action('Lihat Detail', $r->companyInboxUrl());
    }
}
```

- [ ] **Step 5: Jalankan tes**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/HiringDecision.php app/Notifications/JobOffer*.php app/Notifications/ProcessClosed.php tests/Feature/HiringDecisionTest.php
git commit -m "feat(hiring): layanan HiringDecision (tawarkan, ambil, tolak) + email

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Pengingat dan kedaluwarsa terjadwal

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/HiringDecisionTest.php`

- [ ] **Step 1: Tambah tes yang gagal**

Tambahkan `use App\Notifications\JobOfferExpired;` dan `use App\Notifications\JobOfferReminder;` lalu:

```php
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
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --compact --filter=test_job_offer_gets_one_reminder_then_expires`
Expected: FAIL (`The command "kabelota:job-offers" does not exist.`).

- [ ] **Step 3: Perintah terjadwal**

Di `routes/console.php`, tepat di bawah blok `kabelota:close-expired`, tambahkan:

```php
Artisan::command('kabelota:job-offers', function (\App\Services\HiringDecision $hiring) {
    $reminded = $hiring->remindDue();
    $expired = $hiring->expireDue();
    $this->info("{$reminded} pengingat terkirim, {$expired} tawaran kerja kedaluwarsa.");
})->purpose('Ingatkan talenta dan tutup tawaran kerja yang lewat 7 hari');
```

Di bawah `Schedule::command('kabelota:close-expired')->dailyAt('00:10');` tambahkan:

```php
Schedule::command('kabelota:job-offers')->dailyAt('07:30');
```

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add routes/console.php tests/Feature/HiringDecisionTest.php
git commit -m "feat(hiring): pengingat dan kedaluwarsa tawaran kerja terjadwal

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Rute dan controller

**Files:**
- Modify: `routes/web.php` (grup talenta baris 65-73, grup perusahaan baris 76-88)
- Modify: `app/Http/Controllers/TalentInboxController.php`, `app/Http/Controllers/CompanyDashboardController.php`, `app/Models/User.php:92-97`
- Test: `tests/Feature/HiringDecisionTest.php`

- [ ] **Step 1: Tambah tes yang gagal**

```php
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
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: FAIL (`Route [company.job-offer.store] not defined.`).

- [ ] **Step 3: Rute**

Di `routes/web.php`, di grup talenta (setelah baris `talent.offers.respond`):

```php
    Route::post('/tawaran-kerja/{type}/{id}/ambil', [TalentInboxController::class, 'acceptJob'])->whereIn('type', ['rekrut', 'lamaran'])->whereNumber('id')->middleware('throttle:20,1')->name('talent.job-offer.accept');
    Route::post('/tawaran-kerja/{type}/{id}/tolak', [TalentInboxController::class, 'declineJob'])->whereIn('type', ['rekrut', 'lamaran'])->whereNumber('id')->middleware('throttle:20,1')->name('talent.job-offer.decline');
```

Di grup perusahaan (setelah baris `company.applications.update`):

```php
    Route::post('/perusahaan/{type}/{id}/tawarkan-kerja', [CompanyDashboardController::class, 'offerJob'])->whereIn('type', ['rekrut', 'lamaran'])->whereNumber('id')->middleware('throttle:30,1')->name('company.job-offer.store');
```

- [ ] **Step 4: `TalentInboxController`**

Tambah import:

```php
use App\Services\HiringDecision;
```

Ubah urutan di `offers()` dan kirim proses terbuka ke view:

```php
        return view('talent.offers', [
            'offers' => $talent->offers()->with('company')->orderByRaw("status = 'tawaran_kerja' desc, status = 'menunggu' desc")->latest()->get(),
            'openProcesses' => HiringDecision::openFor($talent),
        ]);
```

Di `applications()`:

```php
        return view('talent.applications', [
            'applications' => $talent->applications()->with('jobPosting.company')->orderByRaw("status = 'tawaran_kerja' desc")->latest()->get(),
            'openProcesses' => HiringDecision::openFor($talent),
        ]);
```

Ubah pesan sukses di `respond()` (logika tidak berubah):

```php
        return redirect()->route('talent.offers')->with('status', $data['decision'] === 'diterima'
            ? "Anda tertarik dengan tawaran {$offer->company_name}. Nomor HP dan email Anda sekarang terlihat oleh perusahaan ini."
            : "Tawaran dari {$offer->company_name} ditolak. Kontak Anda tetap tersembunyi.");
```

Tambah tiga method di akhir kelas:

```php
    public function acceptJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = $this->ownJobOffer($request, $type, $id);
        $data = $request->validate(['farewell' => ['nullable', 'string', 'max:500']], [], ['farewell' => 'pesan']);

        $hiring->accept($record, $data['farewell'] ?? null);

        return redirect()->route($record->talentInboxRoute())->with('status',
            "Selamat! Anda mengambil tawaran {$record->job_offer_position} di {$record->companyName()}. Proses dengan perusahaan lain sudah ditutup dan status Anda kini Terikat kontrak.");
    }

    public function declineJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = $this->ownJobOffer($request, $type, $id);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']], [], ['note' => 'alasan']);

        $hiring->decline($record, $data['note'] ?? null);

        return redirect()->route($record->talentInboxRoute())->with('status', "Tawaran kerja dari {$record->companyName()} ditolak.");
    }

    private function ownJobOffer(Request $request, string $type, int $id): RecruitmentOffer|JobApplication
    {
        $record = HiringDecision::find($type, $id);
        abort_unless($record->talent?->user_id === $request->user()->id, 403);

        return $record;
    }
```

- [ ] **Step 5: `CompanyDashboardController`**

Tambah import `use App\Models\RecruitmentOffer;`, `use App\Services\HiringDecision;`, `use Illuminate\Support\Str;`.

Ganti `orderByRaw` di `applicants()`:

```php
                ->orderByRaw("case status when 'tawaran_kerja' then 0 when 'baru' then 1 when 'ditinjau' then 2 when 'diambil' then 3 else 4 end")
```

Ganti isi `updateApplication()`:

```php
    public function updateApplication(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->jobPosting->company_id === $request->user()->company->id, 403);
        abort_unless(in_array($application->status, JobApplication::COMPANY_STATUSES, true), 422, 'Status lamaran ini sudah final.');

        $data = $request->validate(['status' => ['required', Rule::in(JobApplication::COMPANY_STATUSES)]]);
        $changed = $application->status !== $data['status'];
        $application->update(['status' => $data['status'], 'status_changed_at' => now()]);
        if ($changed && $data['status'] !== 'baru') {
            $application->talent->user?->notify(new \App\Notifications\ApplicationStatusChanged($application));
        }

        return back()->with('status', 'Status lamaran '.Str::before($application->talent->name, ',').' diubah ke '.$application->statusLabel().'.');
    }
```

Tambah method:

```php
    public function offerJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = HiringDecision::find($type, $id);
        abort_unless($record->companyId() === $request->user()->company->id, 403);

        $data = $request->validate([
            'job_offer_position' => ['required', 'string', 'max:120'],
            'job_offer_start_date' => ['required', 'date', 'after_or_equal:today'],
            'job_offer_note' => ['nullable', 'string', 'max:500'],
        ], [], ['job_offer_position' => 'posisi', 'job_offer_start_date' => 'tanggal mulai', 'job_offer_note' => 'catatan']);

        $hiring->offerJob($record, $data);

        return back()->with('status', 'Tawaran kerja terkirim ke '.Str::before($record->talent->name, ',').'. Talenta punya '.HiringDecision::OFFER_DAYS.' hari untuk menjawab.');
    }
```

- [ ] **Step 6: Hitungan badge di `app/Models/User.php`**

Ganti `pendingOfferCount()`:

```php
    /** Offers waiting for this talent: new offers plus final job offers from either flow. */
    public function pendingOfferCount(): int
    {
        return once(fn () => $this->isTalent()
            ? RecruitmentOffer::whereIn('status', ['menunggu', 'tawaran_kerja'])->whereHas('talent', fn ($q) => $q->where('user_id', $this->id))->count()
            : 0);
    }

    public function pendingApplicationOfferCount(): int
    {
        return once(fn () => $this->isTalent()
            ? JobApplication::where('status', 'tawaran_kerja')->whereHas('talent', fn ($q) => $q->where('user_id', $this->id))->count()
            : 0);
    }
```

Pastikan `use App\Models\JobApplication;` tidak diperlukan (kelas sama namespace `App\Models`).

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --compact --filter=HiringDecisionTest`
Expected: tes rute masih FAIL di `assertSee('Ya, ambil tawaran ini')` (view belum ada). Tes lain PASS. Lanjut ke Task 5 sebelum commit.

---

### Task 5: Tampilan talenta

**Files:**
- Create: `resources/views/components/job-offer-panel.blade.php`
- Modify: `resources/views/talent/offers.blade.php`, `resources/views/talent/applications.blade.php`, `resources/css/app.css`

- [ ] **Step 1: Komponen panel tawaran kerja**

`resources/views/components/job-offer-panel.blade.php`:

```blade
@props(['record', 'others'])
@php
    $key = $record->typeKey().'-'.$record->id;
    $closing = $others->reject(fn ($other) => $other->is($record));
@endphp
<div class="job-offer" x-data="{ take: false, decline: false }">
    <p class="lbl"><i class="ph ph-handshake" aria-hidden="true"></i> Tawaran kerja · berlaku {{ $record->jobOfferDaysLeft() }} hari lagi</p>
    <dl class="inbox-meta">
        <div><dt class="lbl">Posisi</dt><dd>{{ $record->job_offer_position }}</dd></div>
        @if ($record->job_offer_start_date)<div><dt class="lbl">Mulai</dt><dd class="mono">{{ $record->job_offer_start_date->translatedFormat('j M Y') }}</dd></div>@endif
        <div><dt class="lbl">Batas jawab</dt><dd class="mono">{{ $record->job_offer_expires_at->translatedFormat('j M Y') }}</dd></div>
    </dl>
    @if ($record->job_offer_note)<p class="inbox-msg">{{ $record->job_offer_note }}</p>@endif

    <div class="inbox-actions">
        <button type="button" class="btn btn-accent" @click="take = true"><i class="ph ph-check" aria-hidden="true"></i> Ambil</button>
        <button type="button" class="btn btn-line" @click="decline = ! decline">Tolak</button>
    </div>

    <form x-show="decline" x-cloak class="reject-box" method="post" action="{{ route('talent.job-offer.decline', [$record->typeKey(), $record->id]) }}">@csrf
        <div class="fld"><label for="jd-{{ $key }}">Alasan <span class="demo-note">(opsional, dikirim ke perusahaan)</span></label>
            <input class="box" id="jd-{{ $key }}" name="note" maxlength="300" placeholder="mis. Lokasi terlalu jauh"></div>
        <div style="display:flex;gap:8px"><button class="btn btn-ink btn-sm" type="submit">Tolak Tawaran Kerja</button><button type="button" class="btn btn-line btn-sm" @click="decline = false">Batal</button></div>
    </form>

    <div class="modal-bg" x-show="take" x-cloak x-transition.opacity @click.self="take = false" @keydown.escape.window="take = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="jt-{{ $key }}" method="post"
            action="{{ route('talent.job-offer.accept', [$record->typeKey(), $record->id]) }}" x-trap.noscroll="take">@csrf
            <h2 id="jt-{{ $key }}">Ambil tawaran {{ $record->job_offer_position }}?</h2>
            <p style="margin-top:8px">{{ $record->companyName() }}@if ($record->job_offer_start_date), mulai {{ $record->job_offer_start_date->translatedFormat('j F Y') }}@endif.</p>
            @if ($closing->isNotEmpty())
                <p style="margin-top:14px">Proses Anda dengan perusahaan berikut akan <b>ditutup otomatis</b>:</p>
                <ul class="closing-list">
                    @foreach ($closing as $other)<li><b>{{ $other->companyName() }}</b> · {{ $other->title() }}</li>@endforeach
                </ul>
                <div class="fld"><label for="jf-{{ $key }}">Pesan untuk perusahaan tersebut <span class="demo-note">(opsional, dikirim sekali)</span></label>
                    <textarea class="box" id="jf-{{ $key }}" name="farewell" rows="3" maxlength="500">Terima kasih atas kesempatannya. Saya sudah menerima tawaran kerja di tempat lain. Semoga kita bisa bekerja sama di proyek berikutnya.</textarea></div>
            @else
                <p style="margin-top:14px" class="demo-note">Tidak ada proses lain yang akan ditutup.</p>
            @endif
            <p class="demo-note" style="margin-top:10px">Status ketersediaan Anda akan menjadi <b>Terikat kontrak</b>. Anda bisa mengubahnya lagi di Profil.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                <button type="button" class="btn btn-line" @click="take = false">Batal</button>
                <button class="btn btn-accent" type="submit">Ya, ambil tawaran ini</button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 2: `resources/views/talent/offers.blade.php`**

1. Ganti paragraf pembuka (`<p>Perusahaan yang ingin...`) menjadi:

```blade
        <p>Perusahaan yang ingin merekrut Anda. Tekan Tertarik untuk membuka nomor HP dan email ke perusahaan itu saja. Tawaran kerja final muncul di bagian atas.</p>
```

2. Ganti tab supaya badge lamaran ikut tampil:

```blade
    <x-page-tabs :items="[['Tawaran', 'talent.offers', auth()->user()->pendingOfferCount()], ['Lamaran Saya', 'talent.applications', auth()->user()->pendingApplicationOfferCount()], ['Profil', 'profile.edit']]" />
```

3. Ganti `<article @class(['inbox-card', 'is-new' => $offer->status === 'menunggu'])>` menjadi:

```blade
        <article @class(['inbox-card', 'is-new' => $offer->status === 'menunggu', 'is-offer' => $offer->isJobOffer(), 'is-past' => in_array($offer->status, ['ditutup', 'kedaluwarsa'], true)])>
```

4. Ganti label tombol `Terima` menjadi `Tertarik`, dan teks kunci di sebelahnya menjadi `Tertarik membuka nomor HP dan email Anda untuk {{ $offer->company_name }} saja.`

5. Ganti seluruh blok `@else ... @endif` di bawah form penolakan (mulai `@else` sampai `@endif` sebelum `</article>`) dengan:

```blade
            @elseif ($offer->isJobOffer())
                <x-job-offer-panel :record="$offer" :others="$openProcesses" />
            @else
                <p class="demo-note">
                    @switch($offer->status)
                        @case('diterima') Anda tertarik {{ $offer->responded_at?->diffForHumans() }}. Perusahaan akan menghubungi Anda, lalu bisa mengirim tawaran kerja final. @break
                        @case('diambil') Anda mengambil tawaran kerja {{ $offer->job_offer_position }}. Selamat bekerja! @break
                        @case('ditutup') Ditutup otomatis karena Anda mengambil tawaran kerja lain. @break
                        @case('kedaluwarsa') Tawaran kerja kedaluwarsa karena tidak dijawab dalam 7 hari. @break
                        @default Dijawab {{ $offer->responded_at?->diffForHumans() }}.
                    @endswitch
                    @if ($offer->response_note) Catatan Anda: "{{ $offer->response_note }}"@endif
                    @if ($offer->closed_note && $offer->status !== 'ditutup') Alasan: "{{ $offer->closed_note }}"@endif
                </p>
            @endif
```

(Baris `@if ($offer->status === 'menunggu')` di atasnya tetap menjadi pembuka rantai `@if/@elseif/@else`.)

- [ ] **Step 3: `resources/views/talent/applications.blade.php`**

1. Ganti paragraf pembuka:

```blade
        <p>Status diperbarui oleh perusahaan. Kalau perusahaan mengirim tawaran kerja, Anda yang memutuskan: ambil atau tolak.</p>
```

2. Ganti tab seperti di Step 2 butir 2.

3. Ganti `<article class="inbox-card">` menjadi:

```blade
        <article @class(['inbox-card', 'is-offer' => $app->isJobOffer(), 'is-past' => in_array($app->status, ['ditutup', 'kedaluwarsa'], true)])>
```

4. Ganti blok `@php($reached = ...)` sampai `</ol>` dengan:

```blade
            @php
                $reached = ['baru' => 0, 'ditinjau' => 1, 'tawaran_kerja' => 2, 'diambil' => 3][$app->status] ?? null;
                $ended = ['ditolak' => 'Tidak lanjut', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa'][$app->status] ?? null;
            @endphp
            <ol class="progress-steps" aria-label="Tahap lamaran">
                @foreach (['Terkirim', 'Ditinjau', 'Tawaran kerja', $ended ?? 'Diambil'] as $i => $label)
                    <li @class(['done' => $reached !== null && $i <= $reached, 'bad' => $ended && $i === 3])>{{ $label }}</li>
                @endforeach
            </ol>
            @if ($app->isJobOffer())
                <x-job-offer-panel :record="$app" :others="$openProcesses" />
            @elseif ($app->status === 'ditutup')
                <p class="demo-note">Ditutup otomatis karena Anda mengambil tawaran kerja lain.</p>
            @elseif ($app->status === 'kedaluwarsa')
                <p class="demo-note">Tawaran kerja kedaluwarsa karena tidak dijawab dalam 7 hari.</p>
            @endif
```

- [ ] **Step 4: CSS** — tambahkan di akhir `resources/css/app.css`:

```css
/* Final job offers (HiringDecision). */
.b-offer { background: var(--orange-soft); color: #9A3D00; }
:root[data-theme="dark"] .b-offer { color: #FFB27A; }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) .b-offer { color: #FFB27A; } }
.inbox-card.is-offer { border-color: var(--orange); box-shadow: 4px 4px 0 var(--orange); }
.inbox-card.is-past { opacity: .72; }
.job-offer { border-top: 1px dashed var(--line-strong); padding-top: 12px; display: grid; gap: 10px; }
.closing-list { margin: 8px 0 12px; padding-left: 18px; display: grid; gap: 4px; }
```

- [ ] **Step 5: Build + tes**

Run: `npm run build && php artisan test --compact --filter=HiringDecisionTest`
Expected: PASS (9 tests).

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/TalentInboxController.php app/Http/Controllers/CompanyDashboardController.php app/Models/User.php resources/views/components/job-offer-panel.blade.php resources/views/talent resources/css/app.css tests/Feature/HiringDecisionTest.php
git commit -m "feat(hiring): rute + halaman talenta untuk ambil/tolak tawaran kerja

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Tampilan perusahaan

**Files:**
- Create: `resources/views/components/job-offer-form.blade.php`
- Modify: `resources/views/company/applicants.blade.php`, `resources/views/company/offers.blade.php`
- Test: `tests/Feature/HiringDecisionTest.php`

- [ ] **Step 1: Tes yang gagal**

```php
    public function test_company_pages_show_job_offer_actions_and_closed_messages(): void
    {
        Notification::fake();
        [$user, $talent] = $this->talent();
        $hrd = $this->hrd();
        $job = $hrd->company->jobPostings()->open()->firstOrFail();
        $application = JobApplication::create(['job_posting_id' => $job->id, 'talent_id' => $talent->id, 'status' => 'ditinjau']);
        $offer = $this->interestedOffer($talent);

        $this->actingAs($hrd)->get(route('company.applicants', $job))->assertOk()->assertSee('Tawarkan kerja')->assertDontSee('081299990000');
        $this->get('/perusahaan/tawaran')->assertOk()->assertSee('Tawarkan kerja')->assertSee('081299990000');

        $this->offerJob($offer);
        app(HiringDecision::class)->accept($offer->fresh(), 'Sampai jumpa di proyek lain.');

        $this->get(route('company.applicants', $job))->assertSee('Ditutup')->assertSee('Sampai jumpa di proyek lain.');
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --compact --filter=test_company_pages_show_job_offer_actions_and_closed_messages`
Expected: FAIL (`Tawarkan kerja` tidak ditemukan).

- [ ] **Step 3: Komponen form perusahaan**

`resources/views/components/job-offer-form.blade.php`:

```blade
@props(['record', 'position' => ''])
@php($key = $record->typeKey().'-'.$record->id)
<div x-data="{ open: @js(old('offer_key') === $key && $errors->any()) }" style="display:contents">
    <button type="button" class="btn btn-accent btn-sm" @click="open = true"><i class="ph ph-handshake" aria-hidden="true"></i> Tawarkan kerja</button>
    <div class="modal-bg" x-show="open" x-cloak x-transition.opacity @click.self="open = false" @keydown.escape.window="open = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="jo-{{ $key }}" method="post"
            action="{{ route('company.job-offer.store', [$record->typeKey(), $record->id]) }}" x-trap.noscroll="open">@csrf
            <input type="hidden" name="offer_key" value="{{ $key }}">
            <h2 id="jo-{{ $key }}">Tawarkan kerja ke {{ \Illuminate\Support\Str::before($record->talent->name, ',') }}</h2>
            <p class="demo-note" style="margin-top:6px">Talenta punya 7 hari untuk menjawab. Kalau diambil, proses talenta dengan perusahaan lain ditutup otomatis.</p>
            <div class="fld" style="margin-top:14px"><label for="jp-{{ $key }}">Posisi</label>
                <input class="box @error('job_offer_position') is-err @enderror" id="jp-{{ $key }}" name="job_offer_position" required maxlength="120" value="{{ old('offer_key') === $key ? old('job_offer_position') : $position }}">
                @if (old('offer_key') === $key)@error('job_offer_position')<span class="err">{{ $message }}</span>@enderror @endif</div>
            <div class="fld"><label for="js-{{ $key }}">Tanggal mulai</label>
                <input class="box @error('job_offer_start_date') is-err @enderror" id="js-{{ $key }}" type="date" name="job_offer_start_date" required min="{{ today()->toDateString() }}" value="{{ old('offer_key') === $key ? old('job_offer_start_date') : '' }}">
                @if (old('offer_key') === $key)@error('job_offer_start_date')<span class="err">{{ $message }}</span>@enderror @endif</div>
            <div class="fld"><label for="jn-{{ $key }}">Catatan <span class="demo-note">(opsional: gaji, lokasi, dokumen yang dibawa)</span></label>
                <textarea class="box" id="jn-{{ $key }}" name="job_offer_note" rows="3" maxlength="500">{{ old('offer_key') === $key ? old('job_offer_note') : '' }}</textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button type="button" class="btn btn-line" @click="open = false">Batal</button>
                <button class="btn btn-accent" type="submit">Kirim Tawaran Kerja</button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 4: `resources/views/company/applicants.blade.php`**

1. Ganti kalimat pembuka `<p>{{ $applications->count() }} pelamar. ...</p>` menjadi:

```blade
        <p>{{ $applications->count() }} pelamar. Tinjau dulu, lalu kirim tawaran kerja. Kontak pelamar terbuka setelah tawaran kerja terkirim.</p>
```

2. Ganti form status (mulai `<form method="post" action="{{ route('company.applications.update', $app) }}"` sampai `</form>`) dan blok kontak (`@if ($app->status === 'diterima') ... @endif`) dengan:

```blade
                @if (in_array($app->status, \App\Models\JobApplication::COMPANY_STATUSES, true))
                    <form method="post" action="{{ route('company.applications.update', $app) }}" class="status-form">@csrf
                        <label class="sr-only" for="st-{{ $app->id }}">Ubah status</label>
                        <select class="box" id="st-{{ $app->id }}" name="status" onchange="this.form.requestSubmit()">
                            @foreach (\App\Models\JobApplication::COMPANY_STATUSES as $key)<option value="{{ $key }}" @selected($app->status === $key)>{{ \App\Models\JobApplication::STATUSES[$key] }}</option>@endforeach
                        </select>
                        <noscript><button class="btn btn-ink btn-sm" type="submit">Simpan</button></noscript>
                    </form>
                @endif
                @if ($app->canOfferJob())
                    <x-job-offer-form :record="$app" :position="$job->title" />
                @endif
                @if ($app->contactVisible())
                    <div class="contact-reveal">
                        <a href="tel:{{ $t->phone }}" class="mono"><i class="ph ph-phone" aria-hidden="true"></i> {{ $t->phone }}</a>
                        <a href="mailto:{{ $t->email }}" class="mono"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $t->email }}</a>
                    </div>
                @endif
            </div>
            @if ($app->isJobOffer())
                <p class="demo-note">Tawaran kerja <b>{{ $app->job_offer_position }}</b> terkirim. Menunggu jawaban talenta, {{ $app->jobOfferDaysLeft() }} hari lagi.</p>
            @elseif ($app->status === 'diambil')
                <p class="demo-note">Talenta mengambil tawaran kerja <b>{{ $app->job_offer_position }}</b>.</p>
            @elseif ($app->status === 'ditutup')
                <p class="demo-note">Ditutup otomatis: talenta mengambil tawaran kerja lain.@if ($app->closed_note) Pesan talenta: "{{ $app->closed_note }}"@endif</p>
            @elseif ($app->status === 'kedaluwarsa')
                <p class="demo-note">Tawaran kerja tidak dijawab dalam 7 hari. Anda bisa mengirimnya lagi.</p>
            @elseif ($app->status === 'ditolak' && $app->closed_note)
                <p class="demo-note">Alasan talenta: "{{ $app->closed_note }}"</p>
            @endif
```

Bagian yang diganti dimulai dari baris `<form method="post" action="{{ route('company.applications.update', $app) }}" class="status-form">` dan berakhir di `</div>` yang menutup `.inbox-actions` (baris tepat setelah `@endif` blok kontak lama). Blok baru sudah berisi `</div>` penutup itu, jadi setelah diganti jumlah `<div>` dan `</div>` di dalam `<article>` tetap seimbang.

- [ ] **Step 5: `resources/views/company/offers.blade.php`**

1. Ganti kalimat pembuka:

```blade
        <p>Kontak talenta terbuka setelah talenta menekan Tertarik. Dari sana Anda bisa mengirim tawaran kerja final.</p>
```

2. Ganti isi `<td>` status (yang berisi badge + `response_note`) dengan:

```blade
                        <td><span class="badge {{ $offer->badgeClass() }}">{{ $offer->statusLabel() }}</span>
                            @if ($offer->response_note)<div class="demo-note" style="margin-top:4px">"{{ $offer->response_note }}"</div>@endif
                            @if ($offer->isJobOffer())<div class="demo-note" style="margin-top:4px">Tawaran kerja {{ $offer->job_offer_position }}, {{ $offer->jobOfferDaysLeft() }} hari lagi</div>@endif
                            @if ($offer->status === 'ditutup')<div class="demo-note" style="margin-top:4px">Talenta mengambil kerja lain.@if ($offer->closed_note) "{{ $offer->closed_note }}"@endif</div>@endif
                            @if ($offer->status === 'ditolak' && $offer->closed_note)<div class="demo-note" style="margin-top:4px">"{{ $offer->closed_note }}"</div>@endif
                            @if ($offer->canOfferJob())<div style="margin-top:8px"><x-job-offer-form :record="$offer" :position="$offer->position" /></div>@endif
                        </td>
```

3. Ganti kondisi kontak `@if ($offer->status === 'diterima')` menjadi `@if ($offer->contactVisible())`, dan teks kunci di `@else` menjadi:

```blade
                                <span class="demo-note"><i class="ph ph-lock-simple" aria-hidden="true"></i> {{ $offer->status === 'menunggu' ? 'Terbuka setelah talenta tertarik' : 'Tetap tersembunyi' }}</span>
```

- [ ] **Step 6: Build + tes**

Run: `npm run build && php artisan test --compact --filter=HiringDecisionTest`
Expected: PASS (10 tests).

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/job-offer-form.blade.php resources/views/company tests/Feature/HiringDecisionTest.php
git commit -m "feat(hiring): perusahaan bisa tawarkan kerja dari pelamar dan tawaran terkirim

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Admin, email lama, tes lama

**Files:**
- Modify: `app/Filament/Resources/RecruitmentOffers/RecruitmentOfferResource.php:41,44`
- Modify: `app/Filament/Resources/JobApplications/JobApplicationResource.php:42`
- Modify: `app/Filament/Widgets/RecruitmentFunnelChart.php:35-41`, `app/Filament/Widgets/KabelotaStats.php:60`, `app/Filament/Widgets/TopCompaniesTable.php:28`
- Modify: `app/Notifications/OfferAnswered.php`, `app/Notifications/ApplicationStatusChanged.php:17`
- Modify: `tests/Feature/KabelotaFlowTest.php`

- [ ] **Step 1: Filament RecruitmentOfferResource**

Pastikan ada `use App\Models\RecruitmentOffer;`. Ganti kolom status dan filter:

```php
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => RecruitmentOffer::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['menunggu' => 'warning', 'diterima' => 'info', 'tawaran_kerja' => 'warning', 'diambil' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray'),
```

```php
            ->filters([SelectFilter::make('status')->options(RecruitmentOffer::STATUSES)]);
```

- [ ] **Step 2: Filament JobApplicationResource** — ganti peta warna:

```php
                    ->color(fn ($state) => ['baru' => 'info', 'ditinjau' => 'warning', 'tawaran_kerja' => 'warning', 'diambil' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray'),
```

- [ ] **Step 3: Widget**

`RecruitmentFunnelChart.php`, ganti `$steps`:

```php
        $steps = [
            'Tawaran terkirim' => (clone $offers)->count(),
            'Talenta tertarik' => (clone $offers)->whereIn('status', ['diterima', 'tawaran_kerja', 'diambil'])->count(),
            'Lamaran masuk' => (clone $apps)->count(),
            'Lamaran ditinjau' => (clone $apps)->where('status', '!=', 'baru')->count(),
            'Tawaran kerja' => (clone $offers)->whereIn('status', ['tawaran_kerja', 'diambil', 'kedaluwarsa'])->count()
                + (clone $apps)->whereIn('status', ['tawaran_kerja', 'diambil', 'kedaluwarsa'])->count(),
            'Diambil' => (clone $offers)->where('status', 'diambil')->count() + (clone $apps)->where('status', 'diambil')->count(),
        ];
```

`KabelotaStats.php:60`:

```php
        $accepted = RecruitmentOffer::where('created_at', '>=', $from)->whereIn('status', ['diterima', 'tawaran_kerja', 'diambil'])->count();
```

`TopCompaniesTable.php:28`:

```php
                'offers as accepted_count' => fn (Builder $q) => $q->where('created_at', '>=', $since)->whereIn('status', ['diterima', 'tawaran_kerja', 'diambil']),
```

- [ ] **Step 4: Email lama**

`app/Notifications/OfferAnswered.php` — ganti isi `message()`:

```php
        $name = \Illuminate\Support\Str::before($this->offer->talent->name, ',');
        $mail = (new MailMessage)->subject($this->offer->status === 'diterima'
            ? "{$name} tertarik dengan tawaran Anda"
            : "{$name} menolak tawaran Anda");

        return $this->offer->status === 'diterima'
            ? $mail->line("{$name} tertarik dengan tawaran untuk posisi **{$this->offer->position}**.")
                ->line('Nomor HP dan email talenta sekarang terlihat. Setelah wawancara, kirim tawaran kerja final dari halaman Tawaran Terkirim.')
                ->action('Lihat Kontak Talenta', route('company.offers'))
            : $mail->line("{$name} menolak tawaran untuk posisi **{$this->offer->position}**.")
                ->lineIf((bool) $this->offer->response_note, 'Catatan: "'.$this->offer->response_note.'"')
                ->action('Cari Talenta Lain', route('talents.index'));
```

`app/Notifications/ApplicationStatusChanged.php` — hapus baris `'diterima' => ...` dari array `$line` (perusahaan tidak bisa lagi memilih status itu; tawaran kerja punya email sendiri).

- [ ] **Step 5: Sesuaikan tes lama di `tests/Feature/KabelotaFlowTest.php`**

1. Di `test_talent_accepts_offer_and_company_sees_contact`, ganti `->assertSee('Terima')` menjadi `->assertSee('Tertarik')`.
2. Di `test_talent_applies_once_and_company_manages_applicant`, ganti tiga baris terakhir (mulai `$this->post(route('company.applications.update', $application), ['status' => 'diterima'])`) dengan:

```php
        $this->post(route('company.applications.update', $application), ['status' => 'ditinjau'])->assertRedirect();
        $this->post(route('company.job-offer.store', ['lamaran', $application->id]), [
            'job_offer_position' => $job->title, 'job_offer_start_date' => today()->addWeek()->toDateString(),
        ])->assertRedirect();
        $this->get(route('company.applicants', $job))->assertSee('081299990000');

        $this->actingAs($user)->get('/lamaran')->assertSee('Tawaran kerja')->assertSee('Ya, ambil tawaran ini');
```

3. Cari sisa pemakaian lain:

Run: `grep -n "'diterima'\|Diterima" tests/Feature/*.php`
Expected: hanya pemakaian `decision => 'diterima'` (tawaran rekrut, masih valid). Kalau ada `assertSee('Diterima')` untuk lamaran, ganti ke `'Tawaran kerja'`.

- [ ] **Step 6: Jalankan seluruh tes**

Run: `php artisan test --compact`
Expected: PASS semua (59 lama + 10 baru = 69). Kalau `AdminDashboardTest` gagal karena label funnel berubah, sesuaikan string label di tes itu dengan `$steps` baru.

- [ ] **Step 7: Commit**

```bash
git add app/Filament app/Notifications/OfferAnswered.php app/Notifications/ApplicationStatusChanged.php tests/Feature/KabelotaFlowTest.php
git commit -m "feat(hiring): status baru di admin, email 'tertarik', tes lama disesuaikan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Uji manual dan rilis

- [ ] **Step 1: Migrasi lokal**

Run: `php artisan migrate`
Expected: `2026_10_15_000001_add_job_offer_columns ... DONE`.

- [ ] **Step 2: Uji alur di browser** (`composer local`, buka `http://localhost:8000`)

1. Masuk cepat **Talenta** → lengkapi profil singkat → lamar 2 lowongan dari dua perusahaan berbeda.
2. Di jendela privat, masuk cepat **HRD** → `Lowongan Saya` → pelamar → ubah ke *Ditinjau* → **Tawarkan kerja** (isi posisi + tanggal mulai).
3. Kembali ke talenta → `Lamaran Saya` → kartu orange *Tawaran kerja* di atas → **Ambil** → modal menampilkan perusahaan lain yang akan ditutup → **Ya, ambil tawaran ini**.
4. Cek: lamaran lain *Ditutup*, profil talenta *Terikat kontrak*, email tercatat di `storage/logs/laravel.log` (atau inbox kalau SMTP lokal aktif).
5. Coba juga mode gelap dan lebar HP (modal tidak terpotong).

- [ ] **Step 3: Perintah terjadwal**

Run: `php artisan kabelota:job-offers`
Expected: `0 pengingat terkirim, 0 tawaran kerja kedaluwarsa.` (atau angka sesuai data).

- [ ] **Step 4: Push**

Push lewat terminal panel (Keychain): `git push origin main`, lalu Deploy di Railway (auto-deploy belum aktif). Di Railway, migrasi jalan otomatis lewat `deploy/railway/start.sh`.
