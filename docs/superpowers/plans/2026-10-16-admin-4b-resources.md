# Admin 4b: Filter, kartu statistik, info lamaran, penjelasan tawaran — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Setiap halaman resource admin punya filter yang berguna dan kartu statistik di atas tabel; Lamaran punya kolom/detail tambahan; Tawaran rekrut punya penjelasan "?".

**Architecture:** Kartu statistik = satu `StatsOverviewWidget` per resource di `app/Filament/Resources/<X>/Widgets/<X>Stats.php`, dipasang lewat `getHeaderWidgets()` di halaman List. Semua angka dari query agregat (tanpa loop per baris). Filter ditambahkan di `table()` resource masing-masing.

**Tech Stack:** Filament 5.9 (Tables filters, StatsOverviewWidget, Actions/modals, Infolists), PHPUnit + Livewire testing helpers (`filterTable`, `assertCanSeeTableRecords`, `assertCanNotSeeTableRecords`).

Spec: [../specs/2026-10-16-admin-design.md](../specs/2026-10-16-admin-design.md) bagian 4b. Perintah dari `/Users/semestaaa/Kuliahan/Penting/Kabelota/app`. Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. `npm run build` sebelum tes bila Blade/CSS berubah. Cek setiap API Filament di `vendor/filament/**` sebelum dipakai; laporkan penyesuaian.

Perubahan dari spec (disetujui pengendali): kartu Talenta "Rata-rata kelengkapan" diganti **"Punya CV"** (persen talenta dengan `cv_path`), karena kelengkapan dihitung per talenta dan terlalu berat untuk kartu.

Kolom yang tersedia: `companies(name,type,city,status,legal_doc_path,nib,verified_at,created_at)`, `job_postings(company_id,title,package,status,payment_proof_path,approved_at,concentration,location,closes_at,created_at)`, `talents(type,concentration,city,availability,is_visible,cv_path,skk_scan_path,skk_verified_at,skk_review_note,created_at)` + `certifications(talent_id,jenjang,expires_at)`, `job_applications(job_posting_id,talent_id,status,company_note,job_offer_*,closed_note,created_at)`, `recruitment_offers(company_id,status,responded_at,job_offered_at,created_at)`. Status lowongan: `menunggu_verifikasi | aktif | ditolak | ditutup`; tayang = `JobPosting::open()`.

Pola tes untuk semua task (letakkan di `tests/Feature/AdminResourcesTest.php`, `RefreshDatabase` + `$seed = true`, `actingAs` admin `User::where('role','admin')`):

```php
Livewire::test(ListCompanies::class)
    ->filterTable('city', 'Sigi')
    ->assertCanSeeTableRecords($inSigi)
    ->assertCanNotSeeTableRecords($elsewhere);

Livewire::test(CompanyStats::class)->assertSee('Menunggu verifikasi')->assertSee((string) $expectedCount);
```

---

### Task 1: Perusahaan

**Files:** Modify `app/Filament/Resources/Companies/CompanyResource.php`, `.../Pages/ListCompanies.php`. Create `.../Widgets/CompanyStats.php`. Test `tests/Feature/AdminResourcesTest.php`.

- Filter baru: `city` (SelectFilter, opsi dari `Company::distinct()->orderBy('city')->pluck('city','city')`), `type` (konsultan/kontraktor), `legal_doc` (TernaryFilter: ada/tidak ada dokumen NIB/SBU, berdasar `legal_doc_path`), `has_live_job` (Filter "Punya lowongan tayang": `whereHas('jobPostings', fn ($q) => $q->open())`), `created_at` (Filter dengan dua DatePicker "Dari"/"Sampai" + indikator aktif).
- `CompanyStats`: **Total perusahaan** · **Menunggu verifikasi** (status `menunggu`, warna warning, deskripsi "perlu dicek") · **Terverifikasi** · **Lowongan tayang** (`JobPosting::open()->count()`).
- Tes: filter city (buat 2 perusahaan dengan kota berbeda yang unik), filter legal_doc true/false, filter has_live_job, angka "Menunggu verifikasi" sama dengan `Company::where('status','menunggu')->count()`.
- Commit: `feat(admin): filter + statistik perusahaan`.

### Task 2: Lowongan

**Files:** `app/Filament/Resources/JobPostings/JobPostingResource.php`, `.../Pages/ListJobPostings.php`, create `.../Widgets/JobPostingStats.php`.

- Filter baru: `package` (opsi dari `config('kabelota.packages')` label), `concentration` (opsi `config('kabelota.concentrations')`), `location` (distinct), `live` (Filter "Sedang tayang" → `->open()`), `payment_pending` (Filter "Bukti bayar menunggu cek" → status `menunggu_verifikasi` + `payment_proof_path` not null), `created_at` (rentang tanggal).
- `JobPostingStats`: **Menunggu cek bayar** · **Tayang** · **Pendapatan bulan ini** (jumlah harga paket lowongan yang `approved_at` bulan ini, format `Rp1.200.000`; hitung dengan `groupBy('package')` lalu kalikan harga config, bukan loop per baris) · **Total pelamar** (`JobApplication::count()`).
- Tes: filter package, live, payment_pending; pendapatan bulan ini cocok dengan perhitungan manual dari data uji (buat 2 lowongan disetujui bulan ini dengan paket berbeda dan pastikan angkanya ikut).
- Commit: `feat(admin): filter + statistik lowongan`.

### Task 3: Talenta

**Files:** `app/Filament/Resources/Talent/TalentResource.php`, `.../Pages/ListTalent.php`, create `.../Widgets/TalentStats.php`.

- Filter baru: `jenjang` (SelectFilter multiple, opsi `config('kabelota.jenjang')` keys; query `whereHas('certifications', fn ($q) => $q->whereIn('jenjang', $values))`), `concentration`, `availability` (enum `App\Enums\Availability` label), `city` (distinct), `skk_expired` (Filter "SKK kedaluwarsa": `whereHas('certifications', fn ($q) => $q->whereDate('expires_at', '<', today()))`), `is_visible` (TernaryFilter "Profil tampil di pencarian"), `has_cv` (TernaryFilter berdasar `cv_path`). Pertahankan filter lama (`type`, `hmts_status`, `skk_queue`).
- Tabel: eager-load `certifications` bila kolom/aksi memakainya (cek lazy loading).
- `TalentStats`: **Total talenta** (deskripsi "N alumni · M mahasiswa") · **Tersedia** (`availability = tersedia`) · **SKK perlu dicek** (query sama dengan filter `skk_queue`) · **Punya CV** (persen, "N dari total").
- Tes: filter jenjang, skk_expired, has_cv false, is_visible false; angka SKK perlu dicek cocok dengan query.
- Commit: `feat(admin): filter + statistik talenta`.

### Task 4: Lamaran

**Files:** `app/Filament/Resources/JobApplications/JobApplicationResource.php`, `.../Pages/ListJobApplications.php`, create `.../Widgets/JobApplicationStats.php`.

- Filter baru: `company` (SelectFilter perusahaan: `whereHas('jobPosting', fn ($q) => $q->whereIn('company_id', ...))`, opsi `Company::orderBy('name')->pluck('name','id')`, searchable), `job_posting_id` (relationship ke judul lowongan, searchable). Pertahankan filter status.
- Kolom baru: `company_note` ("Alasan tolak", `limit(40)`, tooltip teks penuh, toggleable), `job_offer_expires_at` ("Batas jawab", tanggal, hanya terisi untuk tawaran kerja, toggleable).
- Aksi baris **Detail** (`ViewAction` atau `Action` dengan `infolist`/schema read-only): pelamar, lowongan + perusahaan, status, pesan pelamar, catatan perusahaan, posisi + tanggal mulai + batas jawab tawaran kerja, pesan perpisahan (`closed_note`). Bagian yang kosong disembunyikan.
- `JobApplicationStats`: **Lamaran 30 hari** · **Baru belum ditinjau** (status `baru`) · **Tawaran kerja berjalan** (status `tawaran_kerja`) · **Diambil** (status `diambil`).
- Tes: filter company menampilkan lamaran perusahaan itu saja; kolom alasan tolak tampil; aksi Detail bisa di-mount dan menampilkan `company_note` (pakai helper `mountTableAction`/`assertSee` yang tersedia); statistik cocok.
- Commit: `feat(admin): info + filter + statistik lamaran`.

### Task 5: Tawaran rekrut + penjelasan "?"

**Files:** `app/Filament/Resources/RecruitmentOffers/RecruitmentOfferResource.php`, `.../Pages/ListRecruitmentOffers.php`, create `.../Widgets/RecruitmentOfferStats.php`.

- Aksi kepala halaman **"Apa ini?"** (`Action::make('bantuan')->label('Apa ini?')->icon('heroicon-o-question-mark-circle')->color('gray')->modalHeading('Tawaran rekrut')->modalContent(view('filament.help.recruitment-offers'))->modalSubmitAction(false)->modalCancelActionLabel('Tutup')`) di `getHeaderActions()` halaman List. View `resources/views/filament/help/recruitment-offers.blade.php` berisi:
  - Satu kalimat: tawaran rekrut = perusahaan yang lebih dulu menghubungi talenta dari halaman Cari Talenta (berbeda dengan Lamaran, ketika talenta yang melamar lowongan).
  - Daftar status berurutan dengan arti singkat: **Menunggu jawaban** (talenta belum menjawab) → **Tertarik** (talenta setuju dihubungi; kontak terbuka) → **Tawaran kerja** (perusahaan mengirim posisi + tanggal mulai; berlaku 7 hari) → **Diambil** (talenta menerima; proses lain ditutup) / **Ditolak** / **Ditutup** (talenta mengambil kerja di tempat lain) / **Kedaluwarsa** (tidak dijawab 7 hari).
  - Catatan: admin hanya memantau; jawaban datang dari talenta dan perusahaan.
- Kolom status: `->tooltip(fn ($state) => <arti singkat status yang sama>)`.
- Filter baru: `company_id` (perusahaan, searchable).
- `RecruitmentOfferStats`: **Terkirim** (total) · **Tertarik** (pernah tertarik: `responded_at` not null dan (status ≠ ditolak atau `job_offered_at` not null)) · **Tawaran kerja** (`job_offered_at` not null) · **Diambil**.
- Tes: halaman List memuat aksi "Apa ini?" dan modalnya berisi "Tertarik" dan "Kedaluwarsa"; filter company; statistik cocok.
- Commit: `feat(admin): penjelasan tawaran rekrut + filter + statistik`.

---

**Verifikasi akhir:** suite penuh hijau; buka tiap halaman resource di admin lokal dan pastikan kartu statistik tampil di atas tabel (lebar 1440 dan 768), filter berjalan, modal "Apa ini?" terbaca di mode gelap.
