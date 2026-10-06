# Catatan manual Kabelota

Satu tempat untuk semua yang harus dikerjakan manusia: membuat akun layanan, mengisi data dari klien, dan keputusan yang bukan wewenang developer. Kode sudah siap. Centang `[x]` setiap kali satu baris selesai.

Cek otomatis di server produksi kapan saja:

```bash
php artisan kabelota:preflight
```

Semua baris **GAGAL** harus hilang sebelum link dibagikan ke publik. Baris **CEK** boleh tersisa, tapi dibaca dulu.

---

## 1. Minggu ini (demo dan QA)

- [ ] **Pindahkan tag demo.** Jalankan dari folder `app`:

  ```bash
  git tag -f -a DEMO-FINAL demo -m "Versi demo untuk klien" && git push -f origin DEMO-FINAL
  ```

- [ ] **GitHub Actions.** Buka repo → tab **Actions** → workflow "Tes" harus hijau untuk SQLite dan MySQL. Repo privat, jadi hanya pemilik yang bisa melihatnya. Kalau merah, kirim log-nya.
- [ ] **Google Form QA.** Jalankan skrip [qa/buat-form-qa.gs](qa/buat-form-qa.gs) di script.google.com (form + Sheets rekap jadi otomatis; isi pertanyaan ada di [qa/FORM-QA.md](qa/FORM-QA.md)), tambahkan pertanyaan unggah screenshot secara manual, lalu isi link form ke `KABELOTA_QA_FORM_URL` di server QA. Skenario uji untuk anggota: [qa/SKENARIO-QA.md](qa/SKENARIO-QA.md).
- [ ] **Railway (Trial, gratis 30 hari).** Buat service `kabelota-qa` (branch `qa`) dan, menjelang presentasi, `kabelota-demo` (branch `demo`). Langkah lengkap: [../deploy/PANDUAN-RAILWAY.md](../deploy/PANDUAN-RAILWAY.md). Deploy pertama sekaligus menguji Dockerfile; kalau gagal, kirim 20 baris terakhir log-nya.
- [ ] **Brevo untuk email QA.** Daftar di brevo.com, verifikasi Gmail sebagai pengirim, buat API key, lalu isi `BREVO_API_KEY` dan `MAIL_FROM_ADDRESS` di service QA.
- [ ] **Jadikan 1-2 anggota admin** setelah mereka daftar di link QA. Lewat `railway ssh`:

  ```bash
  php artisan kabelota:make-admin email@anggota.com
  ```

## 2. Umami (statistik pengunjung, tanpa cookie)

Kode sudah siap. Script Umami hanya dimuat kalau dua variabel di bawah diisi, dan izin CSP-nya ikut otomatis.

- [ ] Daftar di **cloud.umami.is**. Paket gratis Hobby cukup untuk awal; cek batas event dan jumlah situs terbaru di halaman harganya.
- [ ] **Settings → Websites → Add website**: Name `Kabelota`, Domain `kabelota.id` (atau domain Railway selama belum punya domain). Buat website terpisah untuk QA kalau statistik QA ingin dipantau; jangan campur dengan produksi.
- [ ] Buka website itu → **Edit → Tracking code**, salin `data-website-id` (format seperti `a1b2c3d4-...`).
- [ ] Isi di Variables Railway (atau `.env` di VPS):

  ```
  UMAMI_HOST=https://cloud.umami.is
  UMAMI_WEBSITE_ID=a1b2c3d4-....
  ```

  Railway deploy ulang otomatis. Di VPS jalankan `php artisan optimize`.
- [ ] Tes: buka situs dari HP, lalu lihat halaman **Realtime** di Umami. Kunjunganmu harus muncul dalam beberapa detik.
- [ ] **Opsional: tampilkan di panel admin.** Di Umami buka website → **Edit → Share URL**, aktifkan, salin link-nya ke `UMAMI_SHARE_URL`. Panel "Pengunjung situs" muncul di bawah dashboard `/admin`.
- Klik tombol penting sudah dicatat sebagai event, terlihat di menu **Events** Umami:
  - `daftar-akun`
  - `kirim-lamaran`
  - `kirim-tawaran`
  - `pasang-lowongan`
- Pengunjung yang memakai ad-blocker tidak terhitung, jadi angka Umami adalah batas bawah.
- [ ] Tambahkan satu kalimat di Kebijakan Privasi bahwa situs memakai Umami untuk statistik tanpa cookie dan tanpa data pribadi. Ini bagian dari tugas legal di bagian 3.

## 3. Sebelum launch: data dari klien / HMTS

| Selesai | Data | Di mana diisi |
|---|---|---|
| [ ] | Rekening badan usaha untuk transfer pasang lowongan | `KABELOTA_BANK_NAME`, `KABELOTA_BANK_NUMBER`, `KABELOTA_BANK_HOLDER` |
| [ ] | Nomor WhatsApp Bisnis dan email kontak resmi | `KABELOTA_CONTACT_WHATSAPP`, `KABELOTA_CONTACT_EMAIL` |
| [ ] | 9 isian `[kurung siku]` di Kebijakan Privasi dan Syarat Penggunaan (nama badan usaha, tanggal berlaku, jangka waktu, refund, pengadilan) + kalimat Umami, lalu ditinjau ahli hukum | `resources/views/legal/privacy.blade.php`, `terms.blade.php`. Setelah final, hapus banner draf di `components/legal-page.blade.php` |
| [ ] | Foto asli untuk beranda dan halaman lain + izin pakai logo UNTAD dan HMTS | Taruh di `public/images`, lalu ubah `photos` di `config/kabelota.php` |
| [ ] | Konfirmasi narasi Tentang Kami dan istilah status keanggotaan HMTS | `about.blade.php` (hapus catatan draf), `hmts_statuses` di `config/kabelota.php` |
| [ ] | Konfirmasi daftar jabatan kerja SKK, jenjang, dan harga paket lowongan | `jabatan_kerja` dan `packages` di `config/kabelota.php` |

## 4. Sebelum launch: keputusan klien

| Selesai | Pertanyaan | Kondisi sekarang |
|---|---|---|
| [ ] | Boleh tidak perusahaan terverifikasi membuka CV talenta **sebelum** talenta menerima tawaran? CV biasanya memuat nomor HP | Boleh, selama profil tidak disembunyikan. Bisa diubah supaya CV ikut terbuka hanya setelah tawaran diterima |
| [ ] | Saat akun perusahaan dihapus, apakah catatan pembayaran lowongan perlu disimpan untuk pajak? | Sekarang ikut terhapus. Kebijakan Privasi menyebut catatan transaksi bisa disimpan lebih lama, jadi salah satunya perlu disamakan |
| [ ] | Perlu OTP lewat email setiap login untuk akun perusahaan? | Belum ada. Admin sudah memakai 2FA |

## 5. Sebelum launch: layanan produksi

| Selesai | Tugas | Variabel |
|---|---|---|
| [ ] | Railway **Hobby** (US$5/bulan, perlu kartu; Jenius biasanya diterima) + service `kabelota-prod` dari branch `main`. Data asli tidak boleh di akun Trial karena volume Trial dihapus setelah kreditnya habis | [railway/variables.production.env](../deploy/railway/variables.production.env) |
| [ ] | Beli domain (mis. `kabelota.id`), arahkan ke Railway: Settings → Networking → Custom Domain. HTTPS otomatis | `APP_URL` |
| [ ] | Brevo: verifikasi domain (SPF + DKIM) supaya bisa mengirim dari `noreply@kabelota.id` tanpa masuk spam | `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` |
| [ ] | Email yang benar-benar dibaca admin, untuk alert dan pesan Kontak | `KABELOTA_OPS_EMAIL` |
| [ ] | Backup di luar server: bucket Cloudflare R2 (gratis 10 GB). Isi kredensial, lalu `BACKUP_DISK=backups,s3` | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_DEFAULT_REGION=auto` |
| [ ] | Umami untuk domain produksi (bagian 2) | `UMAMI_HOST`, `UMAMI_WEBSITE_ID`, `UMAMI_SHARE_URL` |
| [ ] | Google Search Console: tambah domain, kirim `https://domain/sitemap.xml` | - |

## 6. Hari launch

- [ ] Deploy `main` ke `kabelota-prod` dengan `KABELOTA_DEMO=false` dan `APP_KEY` baru. Jangan pakai kunci demo atau QA.
- [ ] Lewat `railway ssh`, jalankan `php artisan kabelota:preflight` sampai tidak ada **GAGAL**.
- [ ] Login admin, pasang 2FA (Google Authenticator), dan **simpan kode pemulihan** di tempat aman.
- [ ] Uji dari HP dengan data seluler:
  - daftar talenta, cek email verifikasi masuk, isi profil, unggah foto dan CV;
  - daftar perusahaan, admin memverifikasi, pasang lowongan dengan bukti transfer, admin menyetujui;
  - Ajukan Rekrut → talenta menerima → kontak terbuka;
  - Lupa kata sandi dan Unduh data saya;
  - dashboard `/admin` menampilkan angka yang masuk akal.
- [ ] Cek `https://domain/robots.txt` (harus ada baris `Sitemap:`), lalu uji satu halaman lowongan di Google Rich Results Test.
- [ ] Tag rilis:

  ```bash
  git tag -a v1.0.0 -m "Launch" && git push origin v1.0.0
  ```

## 7. Catatan laptop (pengembangan)

- Folder `app` memakai **MySQL MAMP** (port 8889, user dan sandi `root`, database `kabelota`). Nyalakan MAMP dulu sebelum `composer dev`, atau halaman akan error koneksi database.
- Email lokal masuk ke **Mailpit**: http://localhost:8025. Mailpit menyala otomatis lewat `brew services`.
- Server pengerjaan jalan di port **8001** (`composer dev`). Versi demo ada di folder `app-demo`: `composer demo` di port 8000, ditambah `composer share:alt` untuk link Cloudflare.
- Data contoh bisa direset kapan saja dengan `php artisan migrate:fresh --seed`. Data contoh sengaja tersebar di 12 minggu terakhir supaya grafik admin terisi.
- Push dari panel Claude kadang menunggu izin **Keychain** macOS. Kalau muncul popup, klik **Always Allow**.

## 8. Di luar kode

- [ ] Pendaftaran merek "Kabelota" di DJKI.
- [ ] Pendaftaran PSE Lingkup Privat di Komdigi.
- [ ] Kontrak, DP, dan serah terima dengan klien.

Rinciannya ada di [STATUS-DAN-LAUNCH.md](STATUS-DAN-LAUNCH.md).
