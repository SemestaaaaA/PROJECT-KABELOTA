# Spec: Alur keputusan talenta (proyek 2 dari 5)

## Context
Kabelota punya dua jalur rekrut yang belum tuntas:
- **Ajukan Rekrut** (`RecruitmentOffer`: `menunggu | diterima | ditolak`). "Terima" sebenarnya berarti *tertarik* (kontak dibuka), bukan setuju bekerja.
- **Lamaran** (`JobApplication`: `baru | ditinjau | diterima | ditolak`). Perusahaan menandai "Diterima" tanpa pernah menanyai talenta.

Akibatnya talenta tidak bisa menyatakan "saya ambil kerja ini", dan perusahaan lain tidak tahu talenta sudah tidak tersedia. Tujuan: talenta mengambil **satu** tawaran kerja final; semua proses lain otomatis ditutup, perusahaan lain diberi tahu (dengan pesan perpisahan opsional), dan proses itu tinggal sebagai riwayat.

Ini proyek 2 dari pecahan besar. Urutan berikutnya: 1 (perbaikan cepat portal: `/untuk-talenta`, `/kontak` saat login, form email `/profil`, tombol "?"), 3 (halaman pelamar + bento perusahaan), 4 (admin bento + filter + statistik), 5 (mode CMS). Masing-masing dapat siklus desain sendiri.

Keputusan yang sudah disetujui:
- Pemicu tutup: hanya saat talenta **Ambil** tawaran kerja final.
- Pendekatan A: status baru di dua tabel lama + satu layanan.
- Tawaran kerja berlaku 7 hari, pengingat hari ke-5, lalu kedaluwarsa.
- Posisi dan tanggal mulai wajib saat menawarkan kerja.

## Alur
| Jalur | Perusahaan | Talenta |
|---|---|---|
| Ajukan Rekrut | Kirim tawaran → *Menunggu* | **Tertarik** (label baru untuk `diterima`, kontak terbuka) / Tolak |
| | **Tawarkan kerja** → *Tawaran kerja* (hanya dari *Tertarik*) | **Ambil** → *Diambil* / Tolak → *Ditolak* |
| Lamaran | (talenta melamar → *Baru*) → *Ditinjau* / *Ditolak* | |
| | **Tawarkan kerja** → *Tawaran kerja* (hanya dari *Ditinjau*; menggantikan "Diterima") | **Ambil** / Tolak |

Saat **Ambil** (satu transaksi, baris dikunci `lockForUpdate`):
1. Record yang dipilih → `diambil`.
2. Semua record terbuka lain milik talenta → `ditutup`, `closed_at`, `closed_note` = pesan perpisahan.
   - Tawaran terbuka: `menunggu`, `diterima` (Tertarik), `tawaran_kerja`.
   - Lamaran terbuka: `baru`, `ditinjau`, `tawaran_kerja`.
3. `talents.availability` → `terikat_kontrak` (talenta bisa mengubahnya lagi).
4. Email: `JobOfferAnswered` ke perusahaan terpilih; `ProcessClosed` (berisi pesan perpisahan) ke tiap perusahaan yang ditutup, satu email per perusahaan.

Kedaluwarsa: `tawaran_kerja` yang lewat `job_offer_expires_at` → `kedaluwarsa`, perusahaan diberi email; perusahaan boleh menawarkan kerja lagi dari status ini. Pengingat ke talenta saat sisa 2 hari.

Status final yang tidak bisa diubah lagi: `diambil`, `ditutup`, `kedaluwarsa` (kecuali perusahaan menawarkan ulang dari `kedaluwarsa`), `ditolak`.

## Data (satu migrasi)
Kedua tabel `recruitment_offers` dan `job_applications`:
- Status baru: `tawaran_kerja`, `diambil`, `ditutup`, `kedaluwarsa`.
- Kolom baru: `job_offer_position` (string, wajib saat menawarkan), `job_offer_start_date` (date, ≥ hari ini), `job_offer_note` (text, opsional), `job_offered_at`, `job_offer_expires_at`, `job_offer_reminded_at`, `closed_at`, `closed_note`.
- Migrasi data: `job_applications.status = diterima` → `tawaran_kerja`, `job_offered_at = now()`, `job_offer_expires_at = now()+7 hari`, `job_offer_position` = judul lowongan, `job_offer_start_date` = null diizinkan untuk data lama.
- `RecruitmentOffer::STATUSES` dan `JobApplication::STATUSES` + `badgeClass()` diperbarui; label `diterima` di tawaran menjadi "Tertarik".

## Layanan: `app/Services/HiringDecision.php`
- `offerJob(Model $record, array $data)`: validasi kepemilikan perusahaan + status asal, set kolom tawaran (berlaku 7 hari), kirim `JobOfferReceived` ke talenta.
- `accept(Model $record, ?string $farewell)`: langkah Ambil di atas.
- `decline(Model $record, ?string $note)`: `ditolak` + `JobOfferAnswered`.
- `expireDue()` dan `remindDue()`: dipakai perintah terjadwal.
- Record bisa `RecruitmentOffer` atau `JobApplication`; keduanya diberi trait kecil `HasJobOffer` (kolom bersama, scope `open()`, `talent()`, `companyUser()`).

Pola yang dipakai ulang:
- Notifikasi berbasis antrean: `app/Notifications/KabelotaMail.php` (contoh `OfferAnswered`, `ApplicationStatusChanged`).
- Perintah terjadwal: pola `kabelota:close-expired` di `routes/console.php` → tambah `kabelota:job-offers` (expire + remind) `dailyAt('07:30')`.
- Pemeriksaan kepemilikan: pola `abort_unless(...)` di `TalentInboxController::respond` dan `CompanyDashboardController::updateApplication`.

## Rute dan controller
- Talenta (`TalentInboxController`): `POST /tawaran-kerja/{type}/{id}/ambil`, `POST /tawaran-kerja/{type}/{id}/tolak` (type = `rekrut|lamaran`), throttle 20/menit.
- Perusahaan (`CompanyDashboardController`): `POST /perusahaan/{type}/{id}/tawarkan-kerja`.
- `updateApplication` tidak lagi menerima `diterima`; hanya `baru|ditinjau|ditolak`. Status final ditolak dengan 422.
- `respond` (Tertarik/Tolak) tidak berubah logikanya, hanya label.

## Tampilan (secukupnya; perombakan besar ada di proyek 3)
- `resources/views/talent/offers.blade.php`, `talent/applications.blade.php`: kartu *Tawaran kerja* di atas (garis orange, posisi, tanggal mulai, catatan, sisa hari), tombol Ambil/Tolak; modal konfirmasi Ambil berisi daftar perusahaan yang akan ditutup, kolom pesan perpisahan (terisi contoh, bisa diedit), info status → Terikat kontrak; bagian "Riwayat" abu-abu tanpa tombol.
- `company/applicants.blade.php`, `company/offers.blade.php`: tombol **Tawarkan kerja** + modal (posisi, tanggal mulai, catatan); lencana status baru; pesan perpisahan tampil di record *Ditutup*.
- Filament `RecruitmentOffers` dan `JobApplications`: label + warna badge untuk status baru.

## Pengujian: `tests/Feature/HiringDecisionTest.php`
1. Ambil → proses lain `ditutup`, email `ProcessClosed` per perusahaan, availability `terikat_kontrak`.
2. Record `ditutup` tidak bisa diubah perusahaan (422).
3. Talenta tidak bisa menjawab tawaran milik orang lain (403); perusahaan tidak bisa menawarkan kerja ke proses perusahaan lain (403).
4. Tawarkan kerja tanpa posisi/tanggal mulai → error validasi; tanggal lampau ditolak.
5. Tawarkan kerja hanya dari `Tertarik` / `Ditinjau`.
6. `kabelota:job-offers`: pengingat di sisa 2 hari (sekali saja), kedaluwarsa setelah 7 hari + email perusahaan.
7. Migrasi memindah lamaran `diterima` lama ke `tawaran_kerja`.
8. Dua Ambil berurutan untuk talenta yang sama: yang kedua ditolak 422.
9. Semua 59 tes lama tetap lulus (sesuaikan tes yang memakai status `diterima` di lamaran).

## Langkah setelah disetujui
1. Simpan spec ini ke `docs/superpowers/specs/2026-10-15-alur-keputusan-talenta-design.md` dan commit.
2. Jalankan skill writing-plans untuk rencana implementasi bertahap.

## Verifikasi
- `php artisan test --compact` (semua lulus).
- Uji manual lokal (`composer local`): masuk cepat HRD → tawarkan kerja ke talenta yang Tertarik; masuk cepat sebagai talenta lain di jendela privat → lamar 2 lowongan; dari talenta, Ambil satu tawaran → cek perusahaan lain melihat *Ditutup* + pesan, email tercatat di log/Mailpit.
- `php artisan kabelota:job-offers` dengan waktu dimundurkan (`travel` di tes) untuk kedaluwarsa.
- Catatan Railway: dengan Serverless aktif, perintah terjadwal hanya jalan saat server bangun; kedaluwarsa bisa tertunda sampai ada pengunjung (dapat diterima untuk QA).
