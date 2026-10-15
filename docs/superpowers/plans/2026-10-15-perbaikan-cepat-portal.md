# Perbaikan Cepat Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Halaman `/untuk-talenta` + nav sesuai peran, `/kontak` tanpa isi nama/email saat login + tombol "?", dan form `/profil` yang rapi (email sejajar, autofill sesuai tema, email terisi otomatis).

**Architecture:** Perubahan Blade + CSS kecil, satu komponen baru `<x-help-tip>`, satu migrasi kolom `contact_messages.user_id`, satu tes baru `tests/Feature/PortalTest.php`.

**Tech Stack:** Laravel 13, Blade + Alpine.js, Filament 5, PHPUnit (`RefreshDatabase` + DemoSeeder).

Spec: [../specs/2026-10-15-perbaikan-cepat-portal-design.md](../specs/2026-10-15-perbaikan-cepat-portal-design.md). Semua perintah dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`. Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

Catatan Blade: `resources/views/components/layouts/app.blade.php` memakai `@php(...)` sebaris (baris 1). Jangan menambah blok `@php ... @endphp` di file itu (Blade salah kompilasi kalau dua bentuk dicampur). Jangan menempelkan directive ke huruf (`kata@if` tidak dikenali).

---

### Task 1: Form profil (email sejajar, autofill, email terisi)

**Files:** Modify `resources/css/app.css`, `resources/views/profile/edit.blade.php:120-122`. Test: create `tests/Feature/PortalTest.php`.

- [ ] **Step 1: Tes gagal** — buat `tests/Feature/PortalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hrd(): User
    {
        return User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
    }

    public function test_profile_email_defaults_to_the_account_email(): void
    {
        $user = User::factory()->create(['role' => 'talenta', 'email' => 'akun@contoh.id']);

        $this->actingAs($user)->get('/profil')->assertOk()
            ->assertSee('value="akun@contoh.id"', false)
            ->assertSee('boleh beda dengan email login');
    }
}
```

- [ ] **Step 2:** `php artisan test --compact --filter=PortalTest` → FAIL.

- [ ] **Step 3: Profil** — ganti blok email di `resources/views/profile/edit.blade.php` (`<div class="fld"><label for="p-email">Email</label>` … `</div>`) dengan:

```blade
                <div class="fld"><label for="p-email">Email</label>
                    <input class="box @error('email') is-err @enderror" id="p-email" name="email" type="email" value="{{ old('email', $t?->email ?? auth()->user()->email) }}" autocomplete="email">
                    <span class="demo-note">Email kontak untuk perusahaan, boleh beda dengan email login.</span>
                    @error('email')<span class="err">{{ $message }}</span>@enderror</div>
```

- [ ] **Step 4: CSS** — tambahkan di akhir `resources/css/app.css`:

```css
/* Side-by-side fields keep their inputs aligned even when one field has a hint below it. */
.fld { align-content: start; }

/* Browser autofill follows the theme instead of its own blue background. */
.box:-webkit-autofill, .box:-webkit-autofill:hover, .box:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--ink);
    -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
    box-shadow: 0 0 0 1000px var(--surface) inset;
    caret-color: var(--ink);
    transition: background-color 9999s ease-out;
}
```

- [ ] **Step 5:** `npm run build && php artisan test --compact --filter=PortalTest` → PASS. Lalu suite penuh → semua lulus.

- [ ] **Step 6: Commit** `git add resources/css/app.css resources/views/profile/edit.blade.php tests/Feature/PortalTest.php && git commit -m "fix(ui): kolom form sejajar, autofill ikut tema, email profil terisi otomatis"` (+ trailer).

---

### Task 2: `<x-help-tip>` + `/kontak` saat login

**Files:** Create `resources/views/components/help-tip.blade.php`, `database/migrations/2026_10_15_000002_add_user_id_to_contact_messages.php`. Modify `resources/views/contact.blade.php`, `app/Http/Controllers/ContactController.php`, `app/Models/ContactMessage.php`, `app/Filament/Resources/ContactMessages/ContactMessageResource.php:37`, `resources/css/app.css`. Test: `tests/Feature/PortalTest.php`.

- [ ] **Step 1: Tes gagal** — tambahkan ke `PortalTest`:

```php
    public function test_guest_still_fills_name_and_email_on_contact(): void
    {
        $this->get('/kontak')->assertOk()->assertSee('id="c-name"', false)->assertSee('Ke mana pesan ini dikirim?');
        $this->post('/kontak', ['topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.'])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_logged_in_user_sends_contact_message_as_their_account(): void
    {
        $user = User::factory()->create(['role' => 'talenta', 'name' => 'Rahmat Uji', 'email' => 'rahmat@contoh.id']);

        $this->actingAs($user)->get('/kontak')->assertOk()
            ->assertDontSee('id="c-name"', false)
            ->assertSee('Dikirim sebagai')->assertSee('rahmat@contoh.id');

        $this->post('/kontak', [
            'name' => 'Nama Palsu', 'email' => 'palsu@contoh.id', 'topic' => 'Kendala akun', 'message' => 'Saya tidak bisa mengunggah CV.',
        ])->assertRedirect('/kontak');

        $message = ContactMessage::latest('id')->firstOrFail();
        $this->assertSame(['Rahmat Uji', 'rahmat@contoh.id', $user->id], [$message->name, $message->email, $message->user_id]);
    }

    public function test_company_contact_message_uses_company_name(): void
    {
        $hrd = $this->hrd();
        $this->actingAs($hrd)->post('/kontak', ['topic' => 'Kerja sama perusahaan', 'message' => 'Kami ingin kerja sama pilot.'])->assertRedirect('/kontak');

        $this->assertSame($hrd->company->name, ContactMessage::latest('id')->value('name'));
    }
```

- [ ] **Step 2:** jalankan → FAIL.

- [ ] **Step 3: Migrasi** `database/migrations/2026_10_15_000002_add_user_id_to_contact_messages.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Messages from signed-in users point at their account, so the admin knows who wrote them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
```

- [ ] **Step 4: Model** `app/Models/ContactMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Controller** — ganti `store()` di `app/Http/Controllers/ContactController.php`:

```php
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'topic' => ['required', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'kb_trap' => ['prohibited'],
        ];
        // Signed-in users write as their account; only guests type a name and email.
        if (! $user) {
            $rules += ['name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'max:120']];
        }

        $data = $request->validate($rules, ['kb_trap.prohibited' => 'Pesan tidak bisa dikirim.'], ['name' => 'nama', 'topic' => 'topik', 'message' => 'pesan']);

        $sender = $user
            ? ['user_id' => $user->id, 'name' => $user->isCompany() ? ($user->company?->name ?? $user->name) : $user->name, 'email' => $user->email]
            : ['name' => $data['name'], 'email' => $data['email']];

        $message = ContactMessage::create($sender + ['topic' => $data['topic'], 'message' => $data['message']]);
        Notification::route('mail', config('kabelota.ops_email'))->notify(new ContactReceived($message));

        return redirect()->route('contact')->with('contact_sent', true);
    }
```

- [ ] **Step 6: Komponen** `resources/views/components/help-tip.blade.php`:

```blade
@props(['label' => 'Penjelasan'])
@php($tipId = 'tip-'.\Illuminate\Support\Str::random(8))
<span class="help-tip" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @keydown.escape="open = false" @click.outside="open = false">
    <button type="button" class="help-tip-btn" aria-label="{{ $label }}" aria-describedby="{{ $tipId }}" :aria-expanded="open.toString()"
        @click="open = true" @focus="open = true" @blur="open = false">?</button>
    <span class="help-tip-pop" id="{{ $tipId }}" role="tooltip" x-show="open" x-cloak x-transition.opacity>{{ $slot }}</span>
</span>
```

- [ ] **Step 7: CSS** — tambahkan di akhir `resources/css/app.css`:

```css
/* "?" help button with a small explanation (x-help-tip). */
.help-tip { position: relative; display: inline-flex; vertical-align: middle; }
.help-tip-btn { width: 22px; height: 22px; border-radius: 50%; border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink); font: 700 12px/1 var(--f-body); cursor: help; display: grid; place-items: center; padding: 0; }
.help-tip-btn:hover, .help-tip-btn[aria-expanded="true"] { border-color: var(--orange); color: var(--orange); }
.help-tip-btn:focus-visible { outline: 2px solid var(--yellow); outline-offset: 2px; }
.help-tip-pop { position: absolute; left: 0; top: calc(100% + 8px); width: max-content; max-width: min(300px, calc(100vw - 32px)); background: #111113; color: #F6F5F0; border: 1px solid var(--orange); border-radius: var(--r); padding: 10px 12px; font: 400 13px/1.45 var(--f-body); text-transform: none; letter-spacing: 0; z-index: 70; box-shadow: 0 10px 24px rgba(17,17,19,.3); }
```

- [ ] **Step 8: View** `resources/views/contact.blade.php`:
  1. Ganti `<h2 style="font:700 20px var(--f-body)">Kirim pesan</h2>` dengan:

```blade
            <h2 style="font:700 20px var(--f-body);display:flex;align-items:center;gap:8px">Kirim pesan
                <x-help-tip label="Ke mana pesan ini dikirim?">Pesan masuk ke panel admin Kabelota (pengurus HMTS) dan notifikasi dikirim ke email admin. Balasan dikirim ke email Anda, biasanya di hari kerja.</x-help-tip></h2>
```

  2. Bungkus blok `<div class="two">` (Nama + Email) menjadi:

```blade
            @auth
                <p class="demo-note">Dikirim sebagai <b>{{ auth()->user()->isCompany() ? (auth()->user()->company?->name ?? auth()->user()->name) : \Illuminate\Support\Str::before(auth()->user()->name, ',') }}</b> · {{ auth()->user()->email }}</p>
            @else
                <div class="two"> … blok Nama + Email yang lama, tidak diubah … </div>
            @endauth
```

- [ ] **Step 9: Admin** — `ContactMessageResource.php:37` deskripsi kolom nama:

```php
                TextColumn::make('name')->label('Nama')->description(fn (ContactMessage $r) => $r->email.($r->user_id ? ' · akun terdaftar' : ''))->searchable(),
```

- [ ] **Step 10:** `npm run build`, `php artisan test --compact --filter=PortalTest` → PASS, suite penuh → lulus (`test_contact_form_stores_message` lama tetap lulus karena dikirim sebagai tamu).

- [ ] **Step 11: Commit** semua file Task 2: `feat(kontak): kirim pesan sebagai akun saat login + tombol penjelasan "?"`.

---

### Task 3: Halaman `/untuk-talenta` + nav sesuai peran

**Files:** Create `resources/views/for-talents.blade.php`. Modify `routes/web.php:25`, `resources/views/components/layouts/app.blade.php` (nav desktop ~94, menu HP ~154, footer ~209). Test: `PortalTest`.

- [ ] **Step 1: Tes gagal**:

```php
    public function test_nav_shows_the_page_for_the_visitors_role(): void
    {
        $this->get('/lowongan')->assertSee('>Untuk Perusahaan</a>', false)->assertDontSee('>Untuk Talenta</a>', false);

        $talent = User::factory()->create(['role' => 'talenta']);
        $this->actingAs($talent)->get('/lowongan')->assertSee('>Untuk Talenta</a>', false)->assertDontSee('>Untuk Perusahaan</a>', false)
            ->assertSee(url('/untuk-perusahaan'), false); // footer keeps both

        $this->actingAs($this->hrd())->get('/lowongan')->assertSee('>Untuk Perusahaan</a>', false)->assertDontSee('>Untuk Talenta</a>', false);
    }

    public function test_for_talents_page(): void
    {
        $this->get('/untuk-talenta')->assertOk()->assertSee('Profil Anda dicari')->assertSee('Daftar Gratis');
        $this->actingAs(User::factory()->create(['role' => 'talenta']))->get('/untuk-talenta')->assertOk()->assertSee('Lengkapi Profil')->assertDontSee('Daftar Gratis');
    }
```

Catatan: nav desktop dan menu HP menulis teks tautan dengan pola berbeda. Menu HP diawali ikon: `<i …></i> Untuk Perusahaan</a>`, jadi `>Untuk Perusahaan</a>` hanya cocok dengan nav desktop dan footer. Footer harus menulis `Untuk Perusahaan` dan `Untuk Talenta` dengan pola `<li><a href="…">Untuk …</a></li>`, sehingga `assertDontSee('>Untuk Talenta</a>')` untuk tamu akan GAGAL. Karena itu footer memakai label **"Info untuk talenta"** dan **"Info untuk perusahaan"** (teks berbeda), sehingga asersi di atas hanya menguji nav desktop. Sesuaikan tes kalau menemukan pola lain, dan laporkan.

- [ ] **Step 2:** jalankan → FAIL.

- [ ] **Step 3: Rute** — di `routes/web.php` di bawah `Route::view('/untuk-perusahaan', 'companies')->name('companies');`:

```php
Route::view('/untuk-talenta', 'for-talents')->name('for-talents');
```

- [ ] **Step 4: View** `resources/views/for-talents.blade.php`:

```blade
<x-layouts.app title="Untuk Talenta · Kabelota" description="Alumni dan mahasiswa Teknik Sipil UNTAD: buat profil gratis, ditemukan perusahaan konstruksi Sulawesi Tengah, dan pilih sendiri tawaran kerja Anda.">
<div class="wrap">
    <div class="co-hero">
        <div>
            <h1>Profil Anda dicari perusahaan konstruksi Sulawesi Tengah.</h1>
            <p class="lead" style="margin-top:16px">Buat profil sekali, tampilkan SKK dan pengalaman Anda, lalu biarkan perusahaan yang datang. Gratis untuk alumni dan mahasiswa.</p>
            <div class="row" style="display:flex;gap:10px;margin-top:22px;flex-wrap:wrap">
                @auth
                    <a class="btn btn-accent" href="{{ route('profile.edit') }}">Lengkapi Profil</a>
                @else
                    <button type="button" class="btn btn-accent" @click="$store.auth.show({ tab: 'daftar', role: 'talenta' })">Daftar Gratis</button>
                @endauth
                <a class="btn btn-ink" href="{{ route('jobs.index') }}">Lihat Lowongan</a>
            </div>
        </div>
        <div class="ph chamfer" role="img" aria-label="{{ config('kabelota.photos.talent.alt') }}" style="background-image:url({{ config('kabelota.photos.talent.src') }})"></div>
    </div>

    <div class="co-points">
        <div><i class="ph ph-gift ph-icon" aria-hidden="true"></i><span><b>Gratis selamanya</b>Profil, melamar lowongan, dan menerima tawaran tidak dipungut biaya.</span></div>
        <div><i class="ph ph-lock-key ph-icon" aria-hidden="true"></i><span><b>Kontak di tangan Anda</b>Nomor HP dan email hanya terbuka untuk perusahaan yang tawarannya Anda tanggapi dengan Tertarik.</span></div>
        <div><i class="ph ph-seal-check ph-icon" aria-hidden="true"></i><span><b>Pengingat SKK</b>Kami mengirim email sebelum sertifikat SKK Anda habis masa berlakunya.</span></div>
        <div><i class="ph ph-handshake ph-icon" aria-hidden="true"></i><span><b>Anda yang memilih</b>Tawaran kerja final Anda jawab sendiri. Setelah Anda mengambil satu, proses lain ditutup dengan rapi.</span></div>
    </div>
</div>

@php($talentFlow = [
    ['ph-user-circle-plus', 'Buat profil', 'Isi jenjang SKK, pengalaman proyek, dan unggah CV dalam empat langkah.'],
    ['ph-magnifying-glass', 'Ditemukan atau melamar', 'Perusahaan mencari Anda, atau Anda melamar lowongan yang cocok.'],
    ['ph-hand-heart', 'Tertarik', 'Tanggapi tawaran dengan Tertarik. Kontak Anda terbuka untuk perusahaan itu saja.'],
    ['ph-handshake', 'Ambil tawaran kerja', 'Pilih satu tawaran kerja final. Proses dengan perusahaan lain ditutup otomatis.'],
])
<section class="wrap flow-h" aria-labelledby="talent-flow-h">
    <h2 class="h2" id="talent-flow-h">Cara kerjanya</h2>
    <ol class="flow" style="--n: 4">
        @foreach ($talentFlow as $i => [$icon, $title, $text])
            <li>
                <div class="ic"><i class="ph {{ $icon }}" aria-hidden="true"></i></div>
                <span class="step">Langkah {{ $i + 1 }}</span>
                <b>{{ $title }}</b>
                <p>{{ $text }}</p>
            </li>
        @endforeach
    </ol>
</section>
</x-layouts.app>
```

(Garis penghubung `svg.wire` di `companies.blade.php` boleh disalin persis ke dalam `@unless ($loop->last) … @endunless` agar tampilannya sama.)

- [ ] **Step 5: Nav** di `resources/views/components/layouts/app.blade.php`:
  - Nav desktop: ganti baris `<a href="{{ route('companies') }}" … >Untuk Perusahaan</a>` dengan

```blade
            @if ($me?->isTalent())
                <a href="{{ route('for-talents') }}" @if (request()->routeIs('for-talents')) aria-current="page" @endif>Untuk Talenta</a>
            @else
                <a href="{{ route('companies') }}" @if (request()->routeIs('companies')) aria-current="page" @endif>Untuk Perusahaan</a>
            @endif
```

  - Menu HP: pola yang sama untuk baris `<i class="ph ph-buildings" …></i> Untuk Perusahaan` (talenta: ikon `ph-user-circle`, teks `Untuk Talenta`, rute `for-talents`).
  - Footer "Platform": ganti `<li><a href="{{ route('companies') }}">Untuk Perusahaan</a></li>` dengan

```blade
                <li><a href="{{ route('for-talents') }}">Info untuk talenta</a></li>
                <li><a href="{{ route('companies') }}">Info untuk perusahaan</a></li>
```

- [ ] **Step 6:** `npm run build`, tes `--filter=PortalTest` → PASS, suite penuh → lulus (tes lama `test_public_pages_render` membuka `/untuk-perusahaan`, tetap lulus).

- [ ] **Step 7: Commit**: `feat(portal): halaman Untuk Talenta + menu nav sesuai peran`.
