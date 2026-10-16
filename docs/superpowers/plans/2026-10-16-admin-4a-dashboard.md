# Admin 4a: Dashboard bento, tema, notifikasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Lonceng notifikasi admin untuk kejadian penting, pesan kontak bertanda belum dibaca, tema admin bermerek Kabelota, dan dashboard Ringkasan berbentuk bento + bagian "Analitik lainnya".

**Architecture:** `App\Support\AdminAlerts` adalah satu-satunya pintu pengiriman notifikasi database Filament ke admin; controller memanggilnya di titik kejadian. Dashboard menimpa `content()` milik `Filament\Pages\Dashboard` dengan dua `Grid`: bento (8 widget) dan `Section` collapsible (6 widget). Tema resmi Filament (Tailwind v4 lewat Vite) memuat font dan kelas bento.

**Tech Stack:** Laravel 13, Filament 5.9 (Schemas, Widgets, Notifications), Livewire 4, Vite + `laravel-vite-plugin` fonts (`@fonts`), PHPUnit.

Spec: [../specs/2026-10-16-admin-design.md](../specs/2026-10-16-admin-design.md). Semua perintah dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`. Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Jalankan `npm run build` sebelum tes setiap kali CSS/Vite/Blade berubah.

**Penting untuk API Filament:** kode Filament di bawah ditulis untuk v5. Sebelum memakai sebuah metode, cek tanda tangannya di `vendor/filament/**` (mis. `grep -rn "function sendToDatabase" vendor/filament/notifications/src`). Kalau berbeda, sesuaikan dan laporkan.

---

### Task 1: AdminAlerts + lonceng + titik kejadian

**Files:** Create `app/Support/AdminAlerts.php`, migrasi tabel notifications (`php artisan make:notifications-table`), `tests/Feature/AdminAlertsTest.php`. Modify `app/Providers/Filament/AdminPanelProvider.php`, `app/Http/Controllers/CompanyProfileController.php` (`update`, setelah `$company->save()`), `app/Http/Controllers/JobPostingFlowController.php` (`store`, setelah lowongan dibuat), `app/Http/Controllers/ProfileController.php` (blok `if ($request->hasFile('skk_scan') || $signature() !== $before)`), `app/Http/Controllers/ContactController.php` (`store`), `app/Providers/AppServiceProvider.php` (blok `Queue::failing`).

- [ ] **Step 1: Tes gagal** `tests/Feature/AdminAlertsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_alert_reaches_every_admin_and_nobody_else(): void
    {
        $second = User::factory()->create(['role' => 'admin']);
        $talent = User::factory()->create(['role' => 'talenta']);

        AdminAlerts::send('contact_message', 'Pesan kontak baru', 'Rina: Kendala akun', '/admin/contact-messages');

        $this->assertSame(1, $this->admin()->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(0, $talent->notifications()->count());
        $this->assertSame('Pesan kontak baru', $this->admin()->notifications()->first()->data['title']);
    }

    public function test_contact_message_raises_an_alert(): void
    {
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $this->assertStringContainsString('Pesan kontak', $this->admin()->notifications()->first()->data['title']);
    }

    public function test_new_job_posting_with_payment_proof_raises_an_alert(): void
    {
        // Reuse the payload of the existing job-posting test in tests/Feature/KabelotaFlowTest.php (search for 'jobs.posting.store').
        $this->markTestIncomplete('Fill in the payload from the existing job-posting test, then assert the admin got a "Bukti bayar" alert.');
    }
}
```

Ganti `markTestIncomplete` dengan payload nyata dari tes lama yang memanggil `jobs.posting.store` (cari dengan `grep -n "jobs.posting.store" tests/Feature/*.php`), lalu tegaskan judul notifikasi admin mengandung `Bukti bayar`. Tambahkan juga dua tes serupa:
  - `test_company_document_upload_raises_an_alert`: payload dari tes lama yang memanggil `company.profile.update` dengan `legal_doc` (cari `company.profile.update`), tegaskan judul mengandung `verifikasi`.
  - `test_changed_skk_raises_an_alert`: payload profil alumni dengan SKK dari tes lama (`profilePayload` + `skk`), tegaskan judul mengandung `SKK`.

- [ ] **Step 2:** jalankan → FAIL (`Class "App\Support\AdminAlerts" not found`).

- [ ] **Step 3: Tabel** — `php artisan make:notifications-table` (membuat migrasi `notifications`).

- [ ] **Step 4: Kelas** `app/Support/AdminAlerts.php`:

```php
<?php

namespace App\Support;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** The admin bell: one place that turns a platform event into a Filament database notification for every admin. */
class AdminAlerts
{
    /** Event keys (also used by per-admin preferences in 4c) and their bell icons. */
    public const EVENTS = [
        'company_verification' => ['Perusahaan menunggu verifikasi', 'heroicon-o-building-office-2'],
        'payment_proof' => ['Bukti bayar lowongan', 'heroicon-o-banknotes'],
        'skk_review' => ['SKK perlu dicek', 'heroicon-o-identification'],
        'contact_message' => ['Pesan kontak', 'heroicon-o-envelope'],
        'job_failed' => ['Job atau email gagal', 'heroicon-o-exclamation-triangle'],
    ];

    public static function send(string $event, string $title, string $body, string $url): void
    {
        $admins = User::where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon(self::EVENTS[$event][1] ?? 'heroicon-o-bell-alert')
            ->iconColor($event === 'job_failed' ? 'danger' : 'warning')
            ->actions([Action::make('buka')->label('Buka')->url($url)->markAsRead()])
            ->sendToDatabase($admins);
    }
}
```

- [ ] **Step 5: Panel** — di `AdminPanelProvider::panel()`, setelah `->profile(...)`: `->databaseNotifications()->databaseNotificationsPolling('30s')`.

- [ ] **Step 6: Titik kejadian** (pakai `App\Support\AdminAlerts`; URL dari `XxxResource::getUrl('index')`):
  - `CompanyProfileController::update`, setelah `$company->save()`: kirim `company_verification` bila perusahaan belum terverifikasi dan (ada file `legal_doc` baru, atau status baru saja dipindah dari `ditolak` ke `menunggu`). Judul `Perusahaan menunggu verifikasi`, isi `{nama} mengunggah dokumen legalitas.`, URL `CompanyResource::getUrl('index')`.
  - `JobPostingFlowController::store`, setelah `$job` dibuat: `payment_proof`, judul `Bukti bayar lowongan masuk`, isi `{perusahaan}: {judul} ({paket}).`, URL `JobPostingResource::getUrl('index')`.
  - `ProfileController::store`, di dalam blok `if ($request->hasFile('skk_scan') || $signature() !== $before)` dan hanya bila `$talent->certifications()->exists()`: `skk_review`, judul `SKK perlu dicek`, isi `{nama talenta} memperbarui data SKK.`, URL `TalentResource::getUrl('index')`.
  - `ContactController::store`, setelah pesan dibuat: `contact_message`, judul `Pesan kontak: {topik}`, isi `{nama}: {pesan dipotong 80 karakter}`, URL `ContactMessageResource::getUrl('index')`.
  - `AppServiceProvider` blok `Queue::failing`, di dalam `if (! app()->runningUnitTests() && Cache::add(...))`: `rescue(fn () => AdminAlerts::send('job_failed', 'Job gagal: '.$job, Str::limit(...), url('/admin')))`.

- [ ] **Step 7:** tes `--filter=AdminAlertsTest` → PASS; suite penuh → lulus.
- [ ] **Step 8: Commit** `feat(admin): lonceng notifikasi untuk kejadian penting`.

---

### Task 2: Pesan kontak dibaca / belum dibaca

**Files:** Create migrasi `database/migrations/2026_10_16_000002_add_read_at_to_contact_messages.php`. Modify `app/Models/ContactMessage.php`, `app/Filament/Resources/ContactMessages/ContactMessageResource.php`. Test: `tests/Feature/AdminAlertsTest.php`.

- [ ] **Step 1: Tes gagal**:

```php
    public function test_contact_messages_track_read_state(): void
    {
        $message = \App\Models\ContactMessage::create(['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);
        $this->assertSame(1, \App\Models\ContactMessage::unread()->count());

        $message->markAsRead();

        $this->assertNotNull($message->fresh()->read_at);
        $this->assertSame(0, \App\Models\ContactMessage::unread()->count());
        $this->assertNull(\App\Filament\Resources\ContactMessages\ContactMessageResource::getNavigationBadge());
    }
```

- [ ] **Step 2:** jalankan → FAIL.
- [ ] **Step 3: Migrasi** — `read_at` timestamp nullable pada `contact_messages` (down: dropColumn).
- [ ] **Step 4: Model** — tambahkan ke `ContactMessage`: cast `read_at => datetime`, `scopeUnread(Builder $q): void { $q->whereNull('read_at'); }`, `markAsRead(): void { $this->read_at ?? $this->update(['read_at' => now()]); }`.
- [ ] **Step 5: Resource** — di `ContactMessageResource`:
  - `getNavigationBadge(): ?string` → jumlah `unread()` sebagai string, `null` bila 0; `getNavigationBadgeColor()` → `'warning'`.
  - Kolom nama: tebal bila belum dibaca (`->weight(fn ($r) => $r->read_at ? null : FontWeight::Bold)`), tambah `IconColumn`/badge kecil "Baru" untuk yang belum dibaca.
  - Aksi baris `ViewAction` (modal infolist: nama, email, topik, pesan lengkap, waktu) yang memanggil `$record->markAsRead()` saat dibuka (`->mountUsing(...)` atau `->after(...)`; pilih yang ada di Filament 5), dan aksi `Action::make('baca')->label('Tandai dibaca')->visible(fn ($r) => ! $r->read_at)->action(fn ($r) => $r->markAsRead())`.
  - Filter `TernaryFilter::make('read_at')->label('Status baca')->nullable()->trueLabel('Sudah dibaca')->falseLabel('Belum dibaca')`.
- [ ] **Step 6:** tes → PASS; suite penuh (termasuk tes lama yang membuka `/admin/contact-messages`) → lulus.
- [ ] **Step 7: Commit** `feat(admin): pesan kontak bertanda dibaca/belum dibaca`.

---

### Task 3: Tema admin Kabelota

**Files:** Create `resources/css/filament/admin/theme.css` (lewat `php artisan make:filament-theme admin`; kalau perintah bertanya, pilih npm). Modify `vite.config.js` (tambah input tema), `AdminPanelProvider` (`->viteTheme(...)`, font, warna, render hook `@fonts`). Test: `tests/Feature/AdminDashboardTest.php`.

- [ ] **Step 1: Tes gagal** — di `AdminDashboardTest`, tambahkan:

```php
    public function test_admin_uses_the_kabelota_theme(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('filament/admin/theme', false);
    }
```

- [ ] **Step 2:** jalankan → FAIL.
- [ ] **Step 3: Tema** — `php artisan make:filament-theme admin`, ikuti instruksi yang dicetak (input Vite + `->viteTheme('resources/css/filament/admin/theme.css')`). Lalu tambahkan di akhir `theme.css`:

```css
/* Kabelota brand on top of the Filament theme. Fonts come from the @fonts render hook. */
:root { --kb-orange: #FF7A1A; --kb-yellow: #F5B800; }
.fi-body { font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif; }
.fi-wi-stats-overview-stat-value, .kb-num { font-family: 'Barlow Condensed', 'Barlow', sans-serif; font-weight: 800; letter-spacing: -.01em; }
.fi-ta-text-item, .fi-in-text-item { font-variant-numeric: tabular-nums; }
.fi-section, .fi-wi-stats-overview-stat { border-radius: 12px; }
.fi-section { box-shadow: 0 1px 2px rgb(17 17 19 / .06); }
.fi-topbar { border-bottom: 3px solid transparent; border-image: linear-gradient(90deg, var(--kb-yellow) 55%, var(--kb-orange)) 1; }

/* Dashboard bento: equal-height cards in each row. */
.kb-bento > * { height: 100%; }
.kb-bento .fi-section { height: 100%; }
.kb-list { display: grid; gap: .5rem; }
.kb-list-item { display: flex; gap: .75rem; align-items: flex-start; padding: .625rem .75rem; border-radius: .5rem; border: 1px solid rgb(0 0 0 / .08); }
.dark .kb-list-item { border-color: rgb(255 255 255 / .08); }
.kb-list-item.is-unread { border-color: var(--kb-orange); }
.kb-dot { width: .5rem; height: .5rem; border-radius: 99px; background: var(--kb-orange); margin-top: .4rem; flex: none; }
.kb-empty { display: grid; justify-items: center; gap: .25rem; padding: 1.5rem 0; color: rgb(113 113 122); text-align: center; }
```

  Periksa nama kelas `fi-*` di atas terhadap HTML admin yang sebenarnya (`curl` halaman `/admin` setelah login, atau lihat `vendor/filament/**/resources/views`). Hapus aturan yang kelasnya tidak ada.
- [ ] **Step 4: Panel** — di `AdminPanelProvider`:
  - `->colors(['primary' => Color::hex('#F5B800'), 'gray' => Color::Zinc])`.
  - `->font('Barlow', provider: \Filament\FontProviders\LocalFontProvider::class)` (cek kelasnya ada di `vendor/filament/filament/src/FontProviders`).
  - Render hook untuk memuat font yang di-host sendiri oleh Vite: `->renderHook(PanelsRenderHook::HEAD_END, fn (): string => \Illuminate\Support\Facades\Blade::render('@fonts'))`.
- [ ] **Step 5:** `npm run build`; tes → PASS; suite penuh → lulus. Buka `/admin` di browser lokal (login admin lokal `admin@kabelota.test`, sandi dari `KABELOTA_ADMIN_PASSWORD` di `.env`) dan pastikan font Barlow tampil dan tidak ada error konsol (CSP: `font-src 'self' data:`).
- [ ] **Step 6: Commit** `feat(admin): tema Kabelota (font, warna, kartu)`.

---

### Task 4: Ringkasan bento + "Analitik lainnya"

**Files:** Create `app/Filament/Widgets/AdminNotificationsWidget.php`, `resources/views/filament/widgets/admin-notifications.blade.php`, `app/Filament/Widgets/InboxWidget.php`, `resources/views/filament/widgets/inbox.blade.php`. Modify `app/Filament/Pages/Dashboard.php`, `columnSpan` di widget yang ada. Test: `tests/Feature/AdminDashboardTest.php`.

- [ ] **Step 1: Tes gagal** — di `AdminDashboardTest`:

```php
    public function test_dashboard_is_a_bento_with_other_analytics_folded(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        \App\Support\AdminAlerts::send('payment_proof', 'Bukti bayar lowongan masuk', 'CV Uji: Drafter (Reguler).', '/admin/job-postings');
        \App\Models\ContactMessage::create(['name' => 'Rina Uji', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        foreach (['Perlu tindakan', 'Notifikasi', 'Pesan masuk', 'Analitik lainnya'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }
        $this->assertTrue(strpos($html, 'Perlu tindakan') < strpos($html, 'Notifikasi'));
        $this->assertTrue(strpos($html, 'Notifikasi') < strpos($html, 'Analitik lainnya'));
        $this->assertStringContainsString('kb-bento', $html);
    }

    public function test_notifications_and_inbox_widgets_show_latest_items(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        \App\Support\AdminAlerts::send('payment_proof', 'Bukti bayar lowongan masuk', 'CV Uji: Drafter (Reguler).', '/admin/job-postings');
        \App\Models\ContactMessage::create(['name' => 'Rina Uji', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Widgets\AdminNotificationsWidget::class)
            ->assertSee('Bukti bayar lowongan masuk')
            ->call('markAllRead');
        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        \Livewire\Livewire::test(\App\Filament\Widgets\InboxWidget::class)->assertSee('Rina Uji');
    }
```

(Widget Filament dimuat malas; karena itu isi widget diuji lewat `Livewire::test`, sedangkan halaman `/admin` hanya diuji urutan judul. Kalau judul widget lazy tidak ada di HTML awal, tegaskan urutan berdasarkan nama komponen/placeholder yang ada di HTML dan laporkan.)

- [ ] **Step 2:** jalankan → FAIL.

- [ ] **Step 3: Widget notifikasi** `app/Filament/Widgets/AdminNotificationsWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** Five latest bell notifications for the signed-in admin, on the dashboard bento. */
class AdminNotificationsWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-notifications';

    protected int|string|array $columnSpan = 1;

    protected static bool $isDiscovered = false;

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'items' => $user->notifications()->latest()->limit(5)->get(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }
}
```

(Cek di vendor apakah `$view` di Filament 5 `protected string` atau `protected static string`, dan apakah `$isDiscovered` tersedia; sesuaikan.)

`resources/views/filament/widgets/admin-notifications.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell" heading="Notifikasi" wire:poll.30s>
        <x-slot name="afterHeader">
            @if ($unread)
                <x-filament::button size="xs" color="gray" wire:click="markAllRead">Tandai semua dibaca ({{ $unread }})</x-filament::button>
            @endif
        </x-slot>
        @if ($items->isEmpty())
            <div class="kb-empty"><x-filament::icon icon="heroicon-o-bell-slash" class="h-8 w-8" /><b>Belum ada notifikasi</b><span>Kejadian penting akan muncul di sini.</span></div>
        @else
            <ul class="kb-list">
                @foreach ($items as $n)
                    @php($url = collect($n->data['actions'] ?? [])->first()['url'] ?? null)
                    <li @class(['kb-list-item', 'is-unread' => ! $n->read_at])>
                        @unless ($n->read_at)<span class="kb-dot" aria-label="Belum dibaca"></span>@endunless
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">@if ($url)<a href="{{ $url }}" class="hover:underline">{{ $n->data['title'] ?? '' }}</a>@else{{ $n->data['title'] ?? '' }}@endif</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $n->data['body'] ?? '' }}</p>
                            <p class="text-xs text-gray-400">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

(Kalau slot `afterHeader` tidak ada di section Filament 5, pakai `headerEnd` atau letakkan tombol di bawah daftar; cek `vendor/filament/support/resources/views/components/section/index.blade.php`. Satu file ini memakai `@php(...)` sebaris saja.)

- [ ] **Step 4: Widget pesan** `app/Filament/Widgets/InboxWidget.php` + `resources/views/filament/widgets/inbox.blade.php` dengan pola yang sama:
  - Data: `ContactMessage::latest()->limit(5)->get()`, `unread = ContactMessage::unread()->count()`.
  - Section `icon="heroicon-o-envelope" heading="Pesan masuk"`; item: nama (tebal + titik orange bila belum dibaca), topik · waktu, potongan pesan 70 karakter; tautan "Buka semua pesan" ke `ContactMessageResource::getUrl('index')`; kosong: "Belum ada pesan".
  - `columnSpan = 1`, `$isDiscovered = false`.

- [ ] **Step 5: Dashboard** `app/Filament/Pages/Dashboard.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Ringkasan';

    /** Most important first: these sit in the bento. */
    private const BENTO = [
        Widgets\ActionQueue::class,
        Widgets\AdminNotificationsWidget::class,
        Widgets\InboxWidget::class,
        Widgets\KabelotaStats::class,
        Widgets\RecruitmentFunnelChart::class,
        Widgets\RegistrationsChart::class,
        Widgets\RevenueChart::class,
        Widgets\ExpiringJobsTable::class,
    ];

    /** Supporting analytics, folded by default and loaded when opened. */
    private const MORE = [
        Widgets\TopCompaniesTable::class,
        Widgets\ConcentrationChart::class,
        Widgets\JenjangChart::class,
        Widgets\LocationChart::class,
        Widgets\DataQualityStats::class,
        Widgets\VisitorsWidget::class,
    ];

    public function content(Schema $schema): Schema
    {
        $grid = fn (array $widgets) => Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($widgets));

        return $schema->components([
            $grid(self::BENTO)->extraAttributes(['class' => 'kb-bento']),
            Section::make('Analitik lainnya')
                ->description('Perusahaan teraktif, konsentrasi, jenjang, domisili, kualitas data, dan pengunjung.')
                ->icon('heroicon-o-chart-bar-square')
                ->collapsible()->collapsed()
                ->schema([$grid(self::MORE)]),
        ]);
    }
}
```

(Hapus `getColumns()` lama. Pastikan `getWidgetsSchemaComponents()` ada di Filament 5 (`vendor/filament/filament/src/Pages/Dashboard.php` memanggilnya). `$isDiscovered = false` pada dua widget baru mencegahnya muncul dua kali; widget lama tetap ditemukan otomatis tetapi hanya dirender lewat `content()` ini.)

- [ ] **Step 6: Lebar widget** — set `columnSpan` agar bento rapi di grid 4 kolom:
  - `ActionQueue`: `['default' => 'full', 'md' => 2, 'xl' => 2]`
  - `KabelotaStats`, `DataQualityStats`, `VisitorsWidget`: `'full'` (sudah)
  - `RecruitmentFunnelChart`, `RegistrationsChart`, `RevenueChart`, `ExpiringJobsTable`, `TopCompaniesTable`, `LocationChart`: `['default' => 'full', 'xl' => 2]`
  - `ConcentrationChart`, `JenjangChart`: `['default' => 'full', 'md' => 1]`
  `$sort` lama boleh dibiarkan (urutan sekarang ditentukan oleh `BENTO`/`MORE`).

- [ ] **Step 7:** `npm run build`; tes `--filter=AdminDashboardTest` → PASS; suite penuh → lulus. Buka `/admin` di browser lokal: cek bento di lebar 1280px dan 768px, bagian "Analitik lainnya" tertutup dan memuat grafik saat dibuka, mode gelap.
- [ ] **Step 8: Commit** `feat(admin): ringkasan bento + notifikasi + pesan masuk + analitik lainnya`.
