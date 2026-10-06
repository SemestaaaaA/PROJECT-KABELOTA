# Kabelota · DEMO#3

Platform tenaga ahli Teknik Sipil Sulawesi Tengah. *Kabelota* dalam bahasa Kaili berarti **kebaikan untuk bersama**.

Diselenggarakan oleh **Himpunan Mahasiswa Teknik Sipil Universitas Tadulako**. Powered by **RINOYA UNTAD**.

> **DEMO#3**: alur Fase 1 sudah tersambung dari ujung ke ujung. Semua nama talenta, perusahaan, dan proyek adalah data contoh dari seeder. Login masih berupa login demo (lihat bagian Mode demo).

## Isi demo

| Halaman | Rute | Isi |
|---|---|---|
| Beranda | `/` | Hero dengan pencarian bertab, statistik langsung dari database, alur kerja 4 langkah (Perusahaan/Talenta), manfaat, lowongan terbaru, cerita nama, FAQ |
| Cari Talenta | `/talenta` | Filter jabatan SKK, jenjang 4-9, pengalaman (1 sampai 15+ tahun), konsentrasi, lokasi (termasuk Luar Sulteng), ketersediaan; tampilan Grid/List; 20 per halaman; mahasiswa ditandai **Intern for Hire** |
| Profil Talenta | `/talenta/{slug}` | CV digital: SKK dan masa berlaku, riwayat proyek. Nomor HP dan email tidak pernah dikirim ke browser |
| Ajukan Rekrut | `POST /talenta/{slug}/tawaran` | Khusus HRD; tawaran disimpan berstatus `menunggu` (email belum dikirim) |
| Buat Profil | `/profil` | Formulir 4 langkah dengan pratinjau kartu langsung dan indikator kelengkapan; foto, CV, scan SKK/transkrip (privat), keahlian software; status Alumni/Mahasiswa dipilih di sini dan bisa diubah dari halaman profil |
| Pasang Lowongan | `/lowongan/pasang` | Khusus HRD: detail, pilih paket, transfer dan unggah bukti, status menunggu verifikasi, lalu tayang (persetujuan admin masih disimulasikan) |
| Lowongan | `/lowongan`, `/lowongan/{id}` | Filter posisi, lokasi, paket; halaman detail; paket Tenaga Ahli tampil paling atas |
| Halaman perusahaan | `/mitra/{slug}` | Profil publik perusahaan terverifikasi dan lowongan aktifnya |
| Tawaran masuk (talenta) | `/tawaran` | Terima atau Tolak; kontak terbuka hanya untuk perusahaan yang diterima |
| Lamaran Saya (talenta) | `/lamaran` | Status tiap lamaran: Terkirim, Ditinjau, Diterima/Tidak lanjut |
| Lowongan Saya (perusahaan) | `/perusahaan/lowongan` | Status bayar/tayang, jumlah pelamar |
| Pelamar (perusahaan) | `/perusahaan/lowongan/{id}/pelamar` | Pratinjau CV, ubah status; kontak terbuka saat Diterima |
| Tawaran Terkirim (perusahaan) | `/perusahaan/tawaran` | Status tawaran; kontak talenta terbuka setelah diterima |
| Legal | `/kebijakan-privasi`, `/syarat-penggunaan` | Draf, perlu ditinjau ahli hukum |
| Untuk Perusahaan | `/untuk-perusahaan` | Keunggulan, biaya lowongan (Rp50rb / Rp100rb / Rp200rb), cara memasang lowongan |
| Tentang Kami | `/tentang` | Cerita nama, alasan HMTS memulai Kabelota (draf, menunggu konfirmasi HMTS), FAQ |
| Kontak | `/kontak` | Instagram, WhatsApp dan email (placeholder), form pesan (disimpan ke database) |

Fitur umum: mode terang/gelap dengan tombol switch, navigasi yang ikut saat scroll, tombol kembali ke atas, popup Masuk/Daftar.

## Menjalankan

Butuh PHP 8.4+, Composer, dan Node 20+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Atau `composer serve` supaya batas unggah 5 MB berlaku. Buka http://127.0.0.1:8000. Saat mengubah tampilan, jalankan `npm run dev` di terminal terpisah.

Dengan Laravel Herd: `herd link kabelota`, lalu buka http://kabelota.test.

Tes: `php artisan test`

## Versi demo, QA, dan pengerjaan

Satu repo, tiga branch. Link demo yang sudah disebar tidak ikut berubah saat pengerjaan berlanjut.

| Branch | Untuk | Berubah kapan |
|---|---|---|
| `demo` (tag `DEMO-FINAL`) | Link demo klien | Hanya perbaikan darurat lewat `git cherry-pick` |
| `qa` | Server QA anggota tim, pita kuning "Versi QA" | Saat rilis QA: `git checkout qa && git merge main` |
| `main` | Pengerjaan harian | Setiap hari |

- **Server gratis** (Oracle Cloud / Google Cloud) untuk demo + QA: [deploy/PANDUAN-SERVER-GRATIS.md](deploy/PANDUAN-SERVER-GRATIS.md)
- **Template Google Form QA**: [docs/qa/FORM-QA.md](docs/qa/FORM-QA.md), dengan skenario uji di [docs/qa/SKENARIO-QA.md](docs/qa/SKENARIO-QA.md)
- **Demo di laptop tanpa server:** folder `../app-demo` (worktree branch `demo`, database sendiri) menjalankan `composer demo` di port 8000. Folder pengerjaan `app` memakai port 8001 (`SERVER_PORT=8001` di `.env`).
- `php artisan kabelota:reset-demo` mengosongkan data dan unggahan lalu mengisi ulang data contoh. `php artisan kabelota:make-admin email` menjadikan akun admin.

## Bagikan link demo (Cloudflare Tunnel)

Laptop menjalankan Kabelota, Cloudflare memberi link publik `https://….trycloudflare.com`. Gratis, tanpa akun Cloudflare. Link hidup selama kedua terminal menyala.

1. Terminal tab 1: `composer demo`, tunggu sampai muncul `Server running on [http://127.0.0.1:8000]`. **Jangan pakai `composer dev` untuk berbagi link**: mode itu memuat CSS dan JavaScript dari `127.0.0.1:5173` yang hanya ada di laptopmu, jadi di perangkat lain halaman tampil tanpa desain.
2. Terminal tab 2 (Cmd+T): `composer share` (atau `composer share:alt` kalau DNS bermasalah), tunggu sekitar 10 detik sampai muncul link `https://….trycloudflare.com`.
3. Buka link itu di HP pakai data seluler. Kalau beranda tampil, kirim link ke klien.
4. Selesai demo: tekan Ctrl+C di kedua tab. Link langsung mati.

Kalau link tidak bisa dibuka dan log tunnel menulis `QUIC connection failed` atau `Allow outbound QUIC traffic on port 7844`, jaringanmu (biasanya WiFi kampus atau kantor) memblokir port itu. Hentikan dengan Ctrl+C lalu jalankan versi HTTP/2:

```bash
cloudflared tunnel --protocol http2 --url http://127.0.0.1:8000
```

Baris `ERR Failed to initialize DNS local resolver` yang muncul **setelah** `Registered tunnel connection` tidak perlu dikhawatirkan; tunnel tetap jalan. Kalau error DNS muncul **sebelum** tunnel tersambung, DNS dari provider internet (misalnya IndiHome) tidak menjawab pencarian alamat Cloudflare. Pilih salah satu:

- `composer share:alt`: tunnel langsung ke alamat IP Cloudflare tanpa DNS.
- Ganti DNS Mac ke `1.1.1.1` dan `8.8.8.8` (System Settings → Wi-Fi → Details → DNS), lalu jalankan `composer share` lagi.

Kalau masih gagal, pindah ke hotspot HP. Link baru juga kadang butuh 15–30 detik sebelum bisa dibuka; error 530 di awal itu normal.

Catatan: link berganti setiap kali dijalankan ulang; laptop harus menyala, online, dan tidak sleep. Kata sandi akun demo ada di `.env`, jangan dibagikan selain ke orang yang perlu masuk sebagai admin. Vercel tidak cocok untuk Kabelota (unggahan file, database, dan queue butuh server yang jalan terus); untuk pilot gunakan VPS.

## Akun dan login

Login sungguhan per peran: **talenta**, **perusahaan**, dan **admin**. Pendaftaran lewat popup Daftar, lalu verifikasi email (di lokal, link verifikasi ada di `storage/logs/laravel.log`).

| Akun lokal (dari seeder) | Email | Kata sandi |
|---|---|---|
| Admin (panel `/admin`) | `admin@kabelota.test` | nilai `KABELOTA_ADMIN_PASSWORD` di `.env` |
| HRD demo (perusahaan terverifikasi) | `hrd@demo.kabelota.test` | nilai `KABELOTA_DEMO_PASSWORD` di `.env` |

**Ganti kedua kata sandi itu sebelum demo dibuka ke publik.**

Perusahaan baru berstatus *Menunggu verifikasi*. Setelah melengkapi profil dan mengunggah NIB/SBU, admin memverifikasi dari panel. Baru setelah itu perusahaan bisa Ajukan Rekrut, membuka CV, dan memasang lowongan.

### Mode demo (`KABELOTA_DEMO=true`)

Di popup Masuk ada tombol **coba sebagai HRD** (masuk ke akun HRD demo) dan **Talenta** (membuat akun talenta baru yang kosong setiap kali). Matikan dengan `KABELOTA_DEMO=false` saat launch.

## Panel admin (Filament)

`/admin`, khusus akun admin.

- **Perusahaan:** lihat NIB/SBU, Verifikasi atau Tolak (dengan alasan)
- **Lowongan:** lihat bukti transfer, Setujui (lowongan tayang) atau Tolak
- **Talenta**, **Tawaran Rekrut** (pantau), **Pesan Kontak**
- **Dasbor:** talenta terdaftar, antrean verifikasi, pendapatan lowongan bulan ini, tawaran rekrut

## Batas unggah

Foto 5 MB (otomatis dikompres jadi WebP 600x600), dokumen PDF 3 MB. PHP bawaan membatasi unggahan 2 MB, jadi jalankan server lokal dengan `composer serve` (memakai `php/kabelota.ini`). Di server produksi, set `upload_max_filesize` dan `post_max_size` yang sama di `php.ini`.

## Statistik di beranda

Dihitung langsung dari database (`HomeController`): talenta terdaftar, perusahaan terverifikasi, lowongan aktif. Setelah pilot, bisa diganti ke talenta yang diterima kerja, rata-rata hari sampai tawaran pertama, atau skor kepuasan dari survei. Jangan menampilkan angka yang belum diukur.

## Email notifikasi

Dikirim lewat queue (`QUEUE_CONNECTION=database`) untuk: tawaran baru, tawaran diterima/ditolak, lamaran baru, status lamaran berubah, perusahaan diverifikasi/ditolak, lowongan disetujui. Juga verifikasi email dan lupa kata sandi. Di lokal (`MAIL_MAILER=log`) isi email masuk ke `storage/logs/laravel.log`.

Queue butuh worker: jalankan `composer dev` (server + queue + Vite sekaligus), atau `php artisan queue:work` di terminal terpisah. Di produksi, worker dijalankan dengan Supervisor dan `MAIL_MAILER` diisi SMTP (mis. Brevo/Resend).

## Belum ada

- Pembayaran otomatis (QRIS/VA), notifikasi WhatsApp, Export CV format tender (Fase 2)
- Rencana teknis sampai launch: Sprint 2 (siap produksi) dan Sprint 3 (SEO, analitik, CI) di docs/STATUS-DAN-LAUNCH.md


## Struktur

| Apa | Di mana |
|---|---|
| Jabatan SKK, jenjang, konsentrasi, lokasi, paket lowongan, kontak, FAQ | `config/kabelota.php` |
| Token desain (hitam-kuning, Barlow, IBM Plex Mono, tema terang/gelap) | `resources/css/app.css` |
| Layout, nav, footer, popup Masuk/Daftar | `resources/views/components/layouts/app.blade.php` |
| Komponen (kartu talenta, kartu lowongan, FAQ, badge) | `resources/views/components/` |
| Data contoh | `database/seeders/DatabaseSeeder.php`, `database/factories/TalentFactory.php` |
| Logo (WebP) | `public/images/brand/` |

Foto di `public/images/` sementara dari Unsplash (Iqro Rinaldi, Mufid Majnun, Heri Susilo). Ganti dengan foto proyek asli sebelum launch.

## Stack

Laravel 13 · Filament 5 · SQLite (demo) · Tailwind CSS v4 · Alpine.js · Phosphor Icons · font Barlow dan IBM Plex Mono di-host sendiri.
