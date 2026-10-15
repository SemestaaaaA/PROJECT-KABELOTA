# Spec: Portal perusahaan (proyek 3 dari 5)

## Context
Perusahaan mendarat di "Lowongan Saya" tanpa ringkasan apa yang perlu ditindaklanjuti. Halaman pelamar memakai dropdown status yang langsung tersimpan saat diganti, tanpa konfirmasi: salah klik bisa menolak pelamar dan langsung mengirim email ke talenta.

## Keputusan
- Dashboard di halaman baru `/perusahaan` (tab pertama + tujuan setelah login).
- Tolak = modal konfirmasi + alasan opsional yang ikut di email.

## Desain
1. **Dashboard `/perusahaan`** (`company.dashboard`), tata letak bento 4 kolom (2 di tablet, 1 di HP). Data dari `App\Support\CompanyDashboardData`:
   - **Perlu tindakan** (lebar 2×2, maks 6 item, tautan): "N pelamar baru di {lowongan}" → pelamar `?status=baru`; "{talenta} tertarik dengan tawaran {posisi}" (tawaran `diterima` yang belum ditawari kerja) → Tawaran Terkirim; "Tawaran kerja untuk {talenta} kedaluwarsa" → halaman terkait. Kosong: "Semua beres".
   - **Tawaran kerja**: jumlah menunggu jawaban, batas jawab terdekat, jumlah diambil.
   - **Lowongan aktif**: jumlah tayang, total pelamar di lowongan tayang, lowongan yang paling cepat berakhir, tombol Pasang Lowongan.
   - **Kontak terbuka**: jumlah talenta yang kontaknya terlihat (tawaran + lamaran dengan `contactVisible`).
   - **Profil perusahaan**: status verifikasi + kelengkapan (logo, tentang, website, NIB/SBU) dalam persen; kartu bergaris orange kalau belum terverifikasi.
   - Tab perusahaan jadi komponen `<x-company-tabs />`: Dashboard · Lowongan Saya · Tawaran Terkirim · Profil Perusahaan. Menu akun (desktop + HP) menambah "Dashboard". `AuthController::homeFor` → dashboard untuk perusahaan terverifikasi.
2. **Halaman pelamar**:
   - Tab filter `?status=` dengan jumlah: Semua · Baru · Ditinjau · Tawaran kerja · Selesai (diambil/ditolak/ditutup/kedaluwarsa).
   - Baris info: jenjang SKK (+ tag "SKK kedaluwarsa"), pengalaman/semester, domisili, waktu melamar.
   - Dropdown diganti tombol: Baru → *Tandai ditinjau* · *Tolak*; Ditinjau → *Tawarkan kerja* · *Tolak*; Ditolak oleh perusahaan → *Tinjau ulang*.
   - Modal Tolak (`<x-reject-applicant>`): radio alasan (Pengalaman belum sesuai / Jenjang SKK belum sesuai / Posisi sudah terisi / Lokasi tidak cocok / Lainnya) + catatan opsional ≤300. Disimpan di `job_applications.company_note` (kolom baru), ikut email `ApplicationStatusChanged` (lewat `plain()`), tampil di Lamaran Saya. Status lain mengosongkan `company_note`.

## Pengujian (`tests/Feature/CompanyPortalTest.php`)
- Perusahaan terverifikasi login → dashboard.
- Kartu dashboard memuat item yang benar; data perusahaan lain tidak muncul.
- Filter `?status=baru` hanya menampilkan pelamar baru.
- Tolak dengan alasan + catatan menyimpan `company_note`, email berisi catatan, talenta melihatnya; alasan di luar daftar ditolak validasi.
- Tombol aksi mengikuti status; tidak ada `<select name="status">` lagi.
