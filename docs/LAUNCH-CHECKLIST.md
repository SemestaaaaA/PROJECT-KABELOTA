# Checklist launch Kabelota

Kode sudah siap launch. Yang tersisa di bawah ini perlu dikerjakan manusia: membuat akun layanan, mengisi data dari klien, dan keputusan yang bukan wewenang developer.

Cara cek cepat kapan saja, di server produksi:

```bash
php artisan kabelota:preflight
```

Semua baris **GAGAL** harus hilang sebelum link dibagikan ke publik. Baris **CEK** boleh tersisa, tapi dibaca dulu.

## 1. Akun dan layanan (Nakita / DevOps)

| # | Tugas | Catatan | Variabel yang diisi |
|---|---|---|---|
| 1 | Pindahkan tag `DEMO-FINAL` ke versi demo terbaru | Lihat perintah di bawah tabel | - |
| 2 | Cek tab **Actions** di GitHub: workflow "Tes" hijau untuk SQLite dan MySQL | Repo privat, jadi hanya bisa dilihat pemilik | - |
| 3 | Railway: service `kabelota-qa` dan `kabelota-demo` | [deploy/PANDUAN-RAILWAY.md](../deploy/PANDUAN-RAILWAY.md). Deploy pertama juga sekaligus menguji Dockerfile | `railway/variables.*.env` |
| 4 | Railway paket Hobby untuk produksi + service `kabelota-prod` dari branch `main` | Data asli tidak boleh di akun Trial (volume dihapus setelah trial habis) | [railway/variables.production.env](../deploy/railway/variables.production.env) |
| 5 | Beli domain (mis. `kabelota.id`), arahkan ke Railway (Settings → Networking → Custom Domain) | HTTPS otomatis | `APP_URL` |
| 6 | Brevo: verifikasi domain (SPF + DKIM) supaya bisa kirim dari `noreply@kabelota.id` | Tanpa ini email mudah masuk spam | `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` |
| 7 | Email yang dibaca admin untuk alert dan pesan Kontak | Bisa Gmail tim | `KABELOTA_OPS_EMAIL` |
| 8 | Backup di luar server: buat bucket Cloudflare R2 (gratis 10 GB) | Isi kredensial R2, lalu `BACKUP_DISK=backups,s3` | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_DEFAULT_REGION=auto` |
| 9 | Umami Cloud (gratis) untuk statistik pengunjung tanpa cookie | Opsional | `UMAMI_HOST`, `UMAMI_WEBSITE_ID` |
| 10 | Google Search Console: tambah domain, kirim `https://domain/sitemap.xml` | Setelah domain hidup | - |
| 11 | Login admin pertama di produksi, pasang 2FA (Google Authenticator), **simpan kode pemulihan** | Wajib karena mode demo mati | - |

Perintah untuk nomor 1, dijalankan dari folder `app`:

```bash
git tag -f -a DEMO-FINAL demo -m "Versi demo untuk klien" && git push -f origin DEMO-FINAL
```

## 2. Data dari klien / HMTS

| # | Data | Di mana diisi |
|---|---|---|
| 12 | Rekening badan usaha untuk transfer pasang lowongan | `KABELOTA_BANK_NAME`, `KABELOTA_BANK_NUMBER`, `KABELOTA_BANK_HOLDER` |
| 13 | Nomor WhatsApp Bisnis dan email kontak resmi | `KABELOTA_CONTACT_WHATSAPP`, `KABELOTA_CONTACT_EMAIL` |
| 14 | 9 isian `[kurung siku]` di Kebijakan Privasi dan Syarat Penggunaan (nama badan usaha, tanggal berlaku, jangka waktu, refund, pengadilan), lalu ditinjau ahli hukum | `resources/views/legal/privacy.blade.php`, `terms.blade.php`. Setelah final, hapus banner draf di `components/legal-page.blade.php` |
| 15 | Foto asli untuk beranda dan halaman lain + izin pakai logo UNTAD dan HMTS | Taruh di `public/images`, lalu ubah `photos` di `config/kabelota.php` |
| 16 | Konfirmasi narasi Tentang Kami dan istilah status keanggotaan HMTS | `about.blade.php` (hapus catatan draf), `hmts_statuses` di `config/kabelota.php` |
| 17 | Konfirmasi daftar jabatan kerja SKK, jenjang, dan harga paket lowongan | `jabatan_kerja` dan `packages` di `config/kabelota.php` |

## 3. Keputusan yang perlu dibuat klien

| # | Pertanyaan | Kondisi sekarang |
|---|---|---|
| 18 | Boleh tidak perusahaan terverifikasi membuka CV talenta **sebelum** talenta menerima tawaran? CV biasanya memuat nomor HP | Boleh, selama profil tidak disembunyikan. Bisa diubah supaya CV ikut terbuka hanya setelah tawaran diterima |
| 19 | Saat akun perusahaan dihapus, apakah catatan pembayaran lowongan perlu disimpan untuk pajak? | Sekarang ikut terhapus. Kebijakan Privasi menyebut catatan transaksi bisa disimpan lebih lama, jadi salah satunya perlu disamakan |
| 20 | Perlu OTP lewat email setiap login untuk akun perusahaan? | Belum ada. Admin sudah memakai 2FA |

## 4. Hari launch

1. Deploy `main` ke `kabelota-prod` dengan `KABELOTA_DEMO=false` dan `APP_KEY` baru (jangan pakai kunci demo/QA).
2. Lewat `railway ssh`, jalankan `php artisan kabelota:preflight` sampai tidak ada **GAGAL**.
3. Login admin dan pasang 2FA (nomor 11).
4. Uji dari HP dengan data seluler:
   - daftar talenta, cek email verifikasi masuk, isi profil, unggah foto dan CV;
   - daftar perusahaan, admin memverifikasi, pasang lowongan dengan bukti transfer, admin menyetujui;
   - Ajukan Rekrut → talenta menerima → kontak terbuka;
   - Lupa kata sandi dan Unduh data saya.
5. Cek `https://domain/robots.txt` (harus ada baris `Sitemap:`) dan uji satu halaman lowongan di Google Rich Results Test.
6. Tag rilis:

   ```bash
   git tag -a v1.0.0 -m "Launch" && git push origin v1.0.0
   ```

## 5. Di luar kode (diingatkan saja)

Pendaftaran merek "Kabelota" di DJKI, pendaftaran PSE Lingkup Privat di Komdigi, dan kontrak + serah terima dengan klien. Rinciannya ada di [STATUS-DAN-LAUNCH.md](STATUS-DAN-LAUNCH.md).
