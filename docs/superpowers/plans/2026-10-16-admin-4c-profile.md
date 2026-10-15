# Admin 4c: Profil admin (riwayat aktivitas, foto, preferensi notifikasi, sesi aktif) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Profil admin (`/admin/profile`) berisi foto, preferensi notifikasi per kejadian, daftar sesi aktif + keluar dari perangkat lain, dan 10 aktivitas terakhir; ada halaman "Riwayat aktivitas" untuk semua aksi admin penting.

**Architecture:** Halaman profil kustom `App\Filament\Pages\Auth\EditProfile` (turunan `Filament\Auth\Pages\EditProfile`) didaftarkan lewat `->profile(EditProfile::class, isSimple: false)`. Data baru: `users.avatar_path`, `users.notification_prefs` (JSON), tabel `admin_activities`. `AdminAlerts::send` (dari 4a) membaca preferensi. Pencatatan aktivitas lewat satu pintu `AdminActivity::record()`.

**Tech Stack:** Laravel 13, Filament 5.9 (Auth EditProfile, Schemas, FileUpload, Radio/ToggleButtons, Actions), `SESSION_DRIVER=database` (tabel `sessions`: id, user_id, ip_address, user_agent, payload, last_activity), PHPUnit.

Spec: [../specs/2026-10-16-admin-design.md](../specs/2026-10-16-admin-design.md) bagian 4c. Perintah dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`. Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Cek setiap API Filament di `vendor/filament/**` sebelum dipakai; laporkan penyesuaian. `npm run build` sebelum tes bila Blade/CSS berubah. Teks antarmuka Bahasa Indonesia, tanpa em-dash. Tes di `tests/Feature/AdminProfileTest.php` (RefreshDatabase, `$seed = true`).

Profil admin saat ini memakai halaman bawaan (`->profile(isSimple: false)`) dengan MFA aplikasi (`->multiFactorAuthentication(...)`); pertahankan bagian nama, email, sandi, dan 2FA bawaan.

---

### Task 1: Riwayat aktivitas admin

- Migrasi `admin_activities`: `id`, `user_id` (foreignId nullable, `nullOnDelete`), `action` (string 40), `nullableMorphs('subject')`, `description` (string 300), `created_at` (timestamp, tanpa `updated_at`), indeks `created_at`.
- Model `App\Models\AdminActivity` (`UPDATED_AT = null`, relasi `user()`, `subject()` morphTo) + `public static function record(string $action, Model $subject, string $description): void` yang memakai `auth()->id()`. Konstanta `ACTIONS` (kunci → label): `company_verified` Perusahaan diverifikasi, `company_rejected` Perusahaan ditolak, `job_approved` Lowongan disetujui, `job_rejected` Lowongan ditolak, `skk_verified` SKK diverifikasi, `skk_rejected` SKK ditolak.
- Panggil `AdminActivity::record()` di dalam `->action()` keenam aksi: `CompanyResource` `verifikasi`/`tolak`, `JobPostingResource` `setujui`/`tolak`, `TalentResource` `verifikasi_skk`/`tolak_skk`. Deskripsi berisi nama subjek (+ alasan untuk penolakan, dipotong 200 karakter).
- Resource baca-saja `App\Filament\Resources\AdminActivities\AdminActivityResource` (label "Riwayat aktivitas", ikon jam, urutan navigasi paling bawah, tanpa create/edit/delete): kolom Waktu (since + tooltip tanggal), Admin, Aksi (badge label), Keterangan; filter admin, aksi, rentang tanggal (pakai `App\Filament\Support\DateRangeFilter` yang sudah ada). Default urut terbaru.
- Tes: memanggil aksi tabel `verifikasi` pada perusahaan berstatus `menunggu` (`Livewire::test(ListCompanies::class)->callTableAction('verifikasi', $company)`) mencatat satu `AdminActivity` dengan `action = company_verified`, `user_id` admin, subjek perusahaan itu; sama untuk `setujui` lowongan dan `tolak_skk` (dengan data catatan); halaman resource riwayat bisa dibuka admin dan menampilkan baris itu; talenta/perusahaan tidak bisa membuka `/admin` (sudah dijaga `canAccessPanel`, cukup satu asersi).
- Commit `feat(admin): riwayat aktivitas admin`.

### Task 2: Halaman profil kustom + foto profil

- Migrasi `users.avatar_path` (string nullable).
- `User implements Filament\Models\Contracts\HasAvatar` dengan `getFilamentAvatarUrl(): ?string` → URL disk `public` bila ada, selain itu `null` (Filament memakai inisial).
- `App\Filament\Pages\Auth\EditProfile` (turunan halaman bawaan): tambahkan di atas komponen bawaan sebuah `FileUpload::make('avatar_path')->label('Foto profil')->image()->avatar()->maxSize(5120)->disk('public')->directory('avatars')` yang menyimpan lewat `App\Support\ImageStore::fit($file, 'avatars')` (re-encode, cek tanda tangan `fit` dan dukungan `TemporaryUploadedFile`), dan menghapus file lama saat diganti/dihapus. Daftarkan: `->profile(\App\Filament\Pages\Auth\EditProfile::class, isSimple: false)`.
- Tes: admin mengunggah gambar palsu (`UploadedFile::fake()->image('a.png', 400, 400)`) lewat `Livewire::test(EditProfile::class)->set('data.avatar_path', ...)` atau helper unggah Filament yang sesuai → `avatar_path` terisi dan file ada di disk `public` (Storage::fake), `getFilamentAvatarUrl()` tidak null; nama/email tetap bisa disimpan seperti sebelumnya.
- Commit `feat(admin): halaman profil kustom + foto profil`.

### Task 3: Preferensi notifikasi

- Migrasi `users.notification_prefs` (json nullable), cast `array`.
- `User::adminAlertChannel(string $event): string` → nilai tersimpan atau `'bell'` (pilihan: `bell` = lonceng saja, `bell_email` = lonceng + email, `off` = mati).
- `AdminAlerts::send`: kirim database notification hanya ke admin dengan kanal ≠ `off`; kirim email (notifikasi baru `App\Notifications\AdminAlertMail` turunan `KabelotaMail`, subjek = judul, isi = body lewat `self::plain()`, tombol "Buka di admin" ke URL) ke admin dengan kanal `bell_email`. Tetap `rescue()`. Email operasional lama (`ContactReceived` ke `ops_email`, `JobFailedAlert`) tidak diubah.
- Profil: section "Notifikasi" dengan satu `Radio`/`ToggleButtons` inline per kejadian di `AdminAlerts::EVENTS` (label dari konstanta), opsi "Lonceng", "Lonceng + email", "Mati", default "Lonceng". Simpan ke `notification_prefs` (mutate data sebelum simpan; jangan biarkan kunci tak dikenal masuk).
- Tes: admin A `off` untuk `contact_message`, admin B `bell_email` → setelah `AdminAlerts::send('contact_message', …)`: A tidak dapat notifikasi, B dapat database notification dan `AdminAlertMail` (Notification::fake hanya untuk mail; atau assert pada `notifications` tabel + `Notification::assertSentTo` untuk mail); default tanpa prefs tetap lonceng saja; menyimpan form profil menulis prefs yang benar dan menolak nilai di luar tiga opsi.
- Commit `feat(admin): preferensi notifikasi per kejadian`.

### Task 4: Sesi aktif + 10 aktivitas terakhir di profil

- Di halaman profil, section "Sesi aktif": daftar baris tabel `sessions` milik admin (urut `last_activity` terbaru): perangkat (browser + sistem operasi dari user agent, parser kecil berbasis regex di `App\Support\DeviceLabel::from(?string $ua): string`, mis. "Chrome di macOS", "Safari di iPhone", fallback "Perangkat tidak dikenal"), IP, "aktif N menit lalu", label **"Perangkat ini"** untuk `session()->getId()`.
- Aksi "Keluar dari perangkat lain" (modal dengan input sandi saat ini, wajib, divalidasi `Hash::check`): `Auth::logoutOtherDevices($password)` lalu hapus baris `sessions` lain milik admin itu; notifikasi sukses "N sesi lain dikeluarkan". Sembunyikan aksi bila tidak ada sesi lain.
- Section "Aktivitas terakhir Anda": 10 `AdminActivity` milik admin ini (aksi, keterangan, waktu) + tautan "Lihat semua" ke resource riwayat (terfilter admin ini bila filter mendukung query string; kalau tidak, ke index).
- Tes: `DeviceLabel` untuk 3–4 user agent nyata (Chrome macOS, Safari iPhone, Firefox Windows, null); halaman profil menampilkan sesi yang disisipkan langsung ke tabel `sessions` untuk admin itu (dan tidak menampilkan sesi admin lain); aksi keluar dengan sandi benar menghapus sesi lain milik admin dan menyisakan sesi saat ini, dengan sandi salah memberi error dan tidak menghapus apa pun; 10 aktivitas terakhir tampil.
- Commit `feat(admin): sesi aktif dan aktivitas terakhir di profil`.

---

**Verifikasi akhir:** suite penuh hijau; buka `/admin/profile` di admin lokal (lebar 1440 dan 768, mode gelap): foto tampil di topbar, preferensi tersimpan, sesi aktif tampil dengan "Perangkat ini", halaman Riwayat aktivitas berisi aksi yang dilakukan.
