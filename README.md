# Kabelota · DEMO#1

Platform tenaga ahli Teknik Sipil Sulawesi Tengah. *Kabelota* dalam bahasa Kaili berarti **kebaikan untuk bersama**.

Diselenggarakan oleh **Himpunan Mahasiswa Teknik Sipil Universitas Tadulako**. Powered by **RINOYA UNTAD**.

> **DEMO#1** adalah demo pertama untuk klien. Semua nama talenta, perusahaan, dan proyek adalah data contoh dari seeder. Login masih berupa login demo (lihat bagian Mode demo).

## Isi DEMO#1

| Halaman | Rute | Isi |
|---|---|---|
| Beranda | `/` | Hero dengan pencarian bertab, statistik langsung dari database, alur kerja 4 langkah (Perusahaan/Talenta), manfaat, lowongan terbaru, cerita nama, FAQ |
| Cari Talenta | `/talenta` | Filter jabatan SKK, jenjang 4-9, pengalaman (1 sampai 15+ tahun), konsentrasi, lokasi (termasuk Luar Sulteng), ketersediaan; tampilan Grid/List; 20 per halaman; mahasiswa ditandai **Intern for Hire** |
| Profil Talenta | `/talenta/{slug}` | CV digital: SKK dan masa berlaku, riwayat proyek. Nomor HP dan email tidak pernah dikirim ke browser |
| Ajukan Rekrut | `POST /talenta/{slug}/tawaran` | Khusus HRD; tawaran disimpan berstatus `menunggu` (email belum dikirim) |
| Buat Profil | `/profil` | Formulir 4 langkah untuk talenta; status Alumni/Mahasiswa dipilih di sini |
| Lowongan | `/lowongan` | Filter posisi, lokasi, paket; paket Tenaga Ahli tampil paling atas |
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

Buka http://127.0.0.1:8000. Saat mengubah tampilan, jalankan `npm run dev` di terminal terpisah.

Dengan Laravel Herd: `herd link kabelota`, lalu buka http://kabelota.test.

Tes: `php artisan test`

## Mode demo

Tombol **Masuk** membuka popup dengan pilihan **coba sebagai HRD** atau **Talenta**. Pilihan ini disimpan di session (`DemoSessionController`), bukan akun sungguhan.

- **HRD demo:** bisa Ajukan Rekrut ke talenta.
- **Talenta demo:** langsung diarahkan ke Buat Profil; profil yang dibuat muncul di pencarian dan bisa diubah lagi.
- Tanpa masuk: Ajukan Rekrut, Lamar, dan Pasang Lowongan membuka popup Masuk/Daftar.

Untuk demo ke klien: masuk sebagai Talenta, buat profil, keluar, lalu masuk sebagai HRD dan cari profil tadi.

## Statistik di beranda

Dihitung langsung dari database (`HomeController`): talenta terdaftar, perusahaan terverifikasi, lowongan aktif. Setelah pilot, bisa diganti ke talenta yang diterima kerja, rata-rata hari sampai tawaran pertama, atau skor kepuasan dari survei. Jangan menampilkan angka yang belum diukur.

## Belum ada di DEMO#1

- Login dan akun sungguhan per peran
- Panel admin (Filament): verifikasi perusahaan, cek bukti transfer
- Unggah CV, transkrip, dan scan SKK (penyimpanan privat)
- Alur Terima/Tolak tawaran di sisi talenta
- Posting lowongan dan unggah bukti transfer
- Email notifikasi lewat queue

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

Laravel 13 · SQLite (demo) · Tailwind CSS v4 · Alpine.js · Phosphor Icons · font Barlow dan IBM Plex Mono di-host sendiri.
