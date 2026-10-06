# Kabelota: status demo dan rencana launching

Diperbarui 7 Oktober 2026. Daftar tugas manual sebelum launch: [CATATAN-MANUAL.md](CATATAN-MANUAL.md). Demo ke klien sekitar 12 Oktober, target launch pilot awal November 2026.

## Status fitur di demo

| Area | Fitur | Status |
|---|---|---|
| Publik | Beranda, statistik dari database, alur kerja, FAQ | ✅ Selesai |
| Publik | Cari Talenta: filter SKK/jenjang/pengalaman/lokasi, Grid/List, Intern for Hire | ✅ Selesai |
| Publik | Profil talenta (kontak tersembunyi), detail lowongan, halaman perusahaan | ✅ Selesai |
| Publik | Tentang Kami, Kontak (form tersimpan), Untuk Perusahaan | ✅ Selesai |
| Publik | Tampilan HP: menu hamburger, filter lipat, formulir dengan tombol menempel | ✅ Selesai |
| Publik | Mode terang/gelap | ✅ Selesai |
| Akun | Daftar, masuk, verifikasi email, lupa kata sandi | ✅ Selesai |
| Talenta | Buat profil 4 langkah, foto/CV/SKK/transkrip, keanggotaan HMTS, ubah status | ✅ Selesai |
| Talenta | Tawaran masuk (Terima/Tolak), Lamar, Lamaran Saya | ✅ Selesai |
| Perusahaan | Profil + verifikasi NIB/SBU, Ajukan Rekrut, Tawaran Terkirim | ✅ Selesai |
| Perusahaan | Pasang lowongan + bukti transfer, Lowongan Saya, Pelamar | ✅ Selesai |
| Admin | Panel Filament: verifikasi perusahaan & pembayaran, talenta, lamaran, tawaran, pesan, dasbor | ✅ Selesai |
| Sistem | Email notifikasi lewat queue (6 kejadian) | ✅ Selesai (lokal masuk log) |
| Sistem | Audit keamanan: CSP, honeypot anti-spam, dokumen profil tersembunyi terkunci, foto/logo di-encode ulang, rate limit semua form, link kembali anti-redirect, `composer audit` + `npm audit` bersih | ✅ Selesai |
| Sistem | SEO: JobPosting (Google for Jobs), Organization, sitemap.xml, robots.txt, canonical, profil talenta noindex | ✅ Selesai |
| Sistem | Analitik Umami (aktif kalau diisi), index database, cache statistik beranda, tes anti N+1 | ✅ Selesai |
| Sistem | UU PDP: catatan waktu persetujuan, Unduh data saya (JSON), hapus akun mandiri | ✅ Selesai |
| Sistem | `php artisan kabelota:preflight` mengecek setelan sebelum launch | ✅ Selesai |
| Admin | Dashboard: antrean tindakan, 6 KPI 30 hari dengan tren, pendaftaran mingguan, pendapatan per paket, konsentrasi, jenjang SKK, alur rekrutmen, domisili, perusahaan teraktif, lowongan segera berakhir, kualitas data, panel Umami | ✅ Selesai |
| Publik | Animasi: loader 0-100% sekali per sesi (±2,2 detik, bisa dilewati), hero muncul bertahap, bar progres antar halaman, skeleton saat filter/halaman berganti, foto fade-in, reveal saat scroll; mati otomatis kalau pengguna memilih kurangi gerakan | ✅ Selesai |
| Sistem | 57 tes otomatis | ✅ Lolos |
| Legal | Kebijakan Privasi, Syarat Penggunaan | ⚠️ Draf, isian [kurung siku] menunggu klien + ahli hukum |
| Konten | Foto hero dan section | ⚠️ Masih stok Unsplash, perlu foto asli |
| Konten | Narasi HMTS di Tentang Kami, istilah status keanggotaan | ⚠️ Perlu konfirmasi HMTS |
| Akun | Pengaturan akun: ganti sandi/email, sembunyikan profil, hapus akun mandiri | ✅ Selesai |
| Admin | Verifikasi SKK talenta (badge + filter Terverifikasi, reset otomatis saat SKK diubah) | ✅ Selesai |
| Sistem | Lowongan kedaluwarsa ditutup otomatis, perusahaan bisa tutup lebih awal, pengingat SKK habis 30 hari | ✅ Selesai |
| Sistem | Halaman error bergaya Kabelota, pratinjau link WhatsApp | ✅ Selesai |
| Sistem | Demo + QA online di Railway (Dockerfile, volume, auto-deploy per branch) | ⚠️ Kit siap, menunggu akun Railway |
| Sistem | Siap produksi: seeder produksi, backup harian, header keamanan, 2FA admin, email bermerek via Brevo API, alert job gagal, CI SQLite + MySQL | ✅ Selesai |
| Sistem | Server produksi, domain, email SMTP sungguhan | ❌ Belum |
| Fase 2 | QRIS/VA otomatis, WhatsApp, Export CV tender, Mode Kebutuhan Tender, impor Excel | ❌ Belum (upsell) |

## Langkah menuju launching

| # | Langkah | Penanggung jawab | Target | Catatan |
|---|---|---|---|---|
| 1 | Demo ke klien, catat masukan, sepakati paket harga | Nakita | 12 Okt | Bawa RAB, Ringkasan Produk, QnA |
| 2 | Tanda tangan kontrak + DP 40% | Nakita + klien | 14 Okt | Tulis kepemilikan source code dan HKI |
| 3 | Daftarkan merek "Kabelota" di DJKI | Klien/pemilik | 15 Okt | Sistem first to file, jangan ditunda |
| 4 | Kumpulkan dari klien/HMTS: foto asli, logo izin UNTAD & HMTS, rekening badan usaha, konfirmasi narasi | Klien + HMTS | 17 Okt | Ganti foto Unsplash |
| 5 | Lengkapi Kebijakan Privasi & Syarat Penggunaan, tinjau ahli hukum | Klien | 24 Okt | Isi semua [kurung siku] |
| 6 | Sewa VPS (Hostinger KVM 2) + domain + SSL | DevOps | 20 Okt | Lokasi server terdekat Indonesia |
| 7 | Deploy produksi: MySQL, `APP_ENV=production`, `APP_DEBUG=false`, `KABELOTA_DEMO=false`, Supervisor untuk queue, backup harian | DevOps | 24 Okt | Set `upload_max_filesize=8M`, `post_max_size=20M` |
| 8 | Email sungguhan: SMTP (Brevo/Resend) + domain pengirim (SPF/DKIM) | DevOps | 24 Okt | Uji verifikasi & notifikasi masuk inbox, bukan spam |
| 9 | Hapus akun mandiri + verifikasi SKK oleh admin | Nakita | 27 Okt | Hapus akun wajib menurut UU PDP |
| 10 | Analitik: Umami + Google Search Console | DevOps | 27 Okt | Tanpa cookie, ramah UU PDP |
| 11 | QA menyeluruh di HP Android/iPhone + desktop, kedua mode tema | QA | 28-31 Okt | Pakai skenario di README |
| 12 | Daftarkan PSE Lingkup Privat di Komdigi | Klien | Sebelum launch | Karena memproses data pribadi & pembayaran |
| 13 | Uji coba pilot: 20-30 alumni, 3-5 perusahaan | Nakita + HMTS | 3-8 Nov | Kumpulkan masukan |
| 14 | Perbaikan dari pilot, lalu launch | Tim | Pertengahan Nov | Pelunasan 30% saat serah terima |
| 15 | Fase 2 (upsell) | Tim | Setelah launch | Sesuai paket Standard/Pro |
