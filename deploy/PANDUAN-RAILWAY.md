# Panduan Railway: satu server gratis untuk QA

Railway menjalankan Kabelota dari repo GitHub. Setiap kali branch di-push, Railway membangun ulang dan memperbaruinya sendiri. Panduan ini memakai **satu service** (`kabelota`) yang berisi akun admin saja. Anggota QA mendaftar sendiri. Data contoh (talenta dan perusahaan palsu) hanya ada di laptop.

Diperiksa terhadap dokumentasi Railway pada Oktober 2026. Kalau tampilan dashboard berbeda dari langkah di bawah, ikuti nama menunya, bukan urutannya.

## Biaya dan batas (gratis)

| Tahap | Kredit | Batas per service |
|---|---|---|
| **Trial**, 30 hari pertama | US$5 sekali pakai, kedaluwarsa 30 hari | 1 GB RAM, volume 0,5 GB, maks. 5 service per project |
| **Free**, setelah trial | US$1 per bulan, tidak menumpuk | 0,5 GB RAM, 1 vCPU, volume 0,5 GB |
| **Hobby** | US$5 per bulan (sudah termasuk pemakaian US$5), perlu kartu | 48 GB RAM, volume 5 GB |

Harga pemakaian: RAM US$10/GB/bulan, CPU US$20/vCPU/bulan, volume US$0,15/GB/bulan. Build tidak dihitung.

Hitungan kasar: satu service 0,5 GB yang menyala terus memakan sekitar US$5/bulan, jauh di atas kredit Free US$1. Karena itu service harus **tidur saat tidak dipakai** (langkah 6).

Penting:
- **Verifikasi GitHub.** Saat daftar, sambungkan akun GitHub supaya trial berstatus *Full Trial*. Tanpa itu (Limited Trial) akses jaringan keluar dan port dibatasi, sehingga email lewat Brevo bisa gagal. Kalau terlanjur, buka `railway.com/verify`.
- **Data volume trial dihapus 30 hari setelah kredit habis.** Untuk QA tidak masalah. Untuk data pilot sungguhan, pindah ke Hobby atau VPS.
- Railway memblokir SMTP, jadi email dikirim lewat API Brevo (langkah 7).

## 1. Siapkan kode

Branch yang dipasang: `main` (atau `qa` kalau kamu memisahkannya). Pastikan sudah di-push ke GitHub dan berisi `Dockerfile`, `railway.json`, dan `deploy/railway/start.sh`.

Buat kunci aplikasi, lalu simpan hasilnya (diawali `base64:`):

```bash
php artisan key:generate --show
```

## 2. Akun dan project

1. Buka **railway.com** → Login → **Login with GitHub**.
2. Sambungkan GitHub untuk verifikasi (lihat catatan di atas).
3. **New Project** → **Deploy from GitHub repo** → izinkan repo `PROJECT-KABELOTA` → pilih repo itu.
4. Deploy pertama akan gagal karena variabel belum diisi. Itu normal.
5. Klik service → **Settings**: Service Name `kabelota`, Source → Branch sesuai langkah 1.

## 3. Volume (database dan unggahan)

Klik kanan service di kanvas → **Attach volume** → Mount path `/data`. Tanpa volume, database SQLite dan semua unggahan hilang setiap deploy.

## 4. Variabel

Service → **Variables** → **Raw Editor**. Tempel isi [railway/variables.qa.env](railway/variables.qa.env), lalu ganti:

- `APP_KEY` = hasil langkah 1;
- `KABELOTA_ADMIN_PASSWORD` = sandi baru, minimal 12 karakter (bukan sandi pribadimu);
- `KABELOTA_OPS_EMAIL` = emailmu.

Klik **Update Variables**.

## 5. Domain

Service → **Settings → Networking → Generate Domain** (isi port `8080` kalau ditanya). Setelah domain `xxxx.up.railway.app` muncul dan service **Active**, buka dari HP. Login admin: email `admin@kabelota.id` (atau nilai `KABELOTA_ADMIN_EMAIL`) dan sandi dari langkah 4. Admin wajib mengaktifkan 2FA (aplikasi authenticator) saat login pertama.

Deploy pertama memakan 3–6 menit. Lihat **Deployments → View logs** kalau macet.

## 6. Supaya tetap gratis: Serverless

Service → **Settings → Deploy → Serverless** → aktifkan, lalu **Redeploy**.

- Service tidur setelah beberapa menit tanpa pengunjung dan tidak memakan kredit.
- Kunjungan pertama setelah tidur butuh 10–30 detik; kalau muncul 502, muat ulang.
- Yang tidak jalan saat tidur: jadwal otomatis (tutup lowongan kedaluwarsa, backup harian, pengingat SKK). Untuk QA ini tidak masalah.
- Pantau menu **Usage** seminggu sekali. Kalau mendekati kredit, hapus service yang tidak dipakai.

## 7. Email lewat Brevo (gratis 300 email/hari)

1. Daftar di **brevo.com**.
2. **Senders → Add a sender**: pakai Gmail-mu, lalu klik link verifikasi di Gmail itu.
3. **SMTP & API → API Keys → Generate a new API key** (diawali `xkeysib-`).
4. Di Variables service: isi `BREVO_API_KEY` dan `MAIL_FROM_ADDRESS` (Gmail yang sudah diverifikasi). `MAIL_MAILER` sudah `brevo`.
5. Tes: daftar akun baru di link Railway. Email verifikasi harus masuk (cek folder spam).

## 8. Google Form QA

Isi `KABELOTA_QA_FORM_URL` dengan link Google Form. Tombol "Laporkan masalah" muncul di pita kuning.

---

## Pemakaian sehari-hari

**Rilis baru.** Railway deploy otomatis setiap branch berubah:

```bash
git push origin main
```

**CLI Railway** (opsional, untuk log dan perintah di server):

```bash
brew install railway
railway login
railway link
railway logs
railway ssh
```

**Menjadikan anggota QA admin**, dari `railway ssh`:

```bash
php artisan kabelota:make-admin email@anggota.com
```

**Reset data QA** (hapus semua akun uji), dari `railway ssh`:

```bash
php artisan migrate:fresh --seed --force
```

Perintah itu menghapus semua tabel dan membuat ulang akun admin saja. Unggahan lama di `/data` tidak ikut terhapus.

## Kalau ada masalah

| Gejala | Coba |
|---|---|
| Build gagal | Deployments → View logs → Build Logs; kirim 20 baris terakhir |
| "Application failed to respond" | Port di Networking = `8080`; `APP_KEY` sudah diisi |
| Error 500 | Deploy Logs; biasanya `APP_KEY` atau `KABELOTA_ADMIN_PASSWORD` kosong atau kurang dari 12 karakter |
| Unggahan hilang setelah deploy | Volume belum terpasang di `/data` |
| Email tidak masuk | `BREVO_API_KEY` benar, `MAIL_FROM_ADDRESS` = sender terverifikasi, akun Railway berstatus Full Trial |
| Kredit habis | Menu Usage; Serverless belum aktif, atau upgrade ke Hobby |

## Catatan

- Link `trycloudflare` dari laptop tetap bisa dipakai sebagai cadangan (`composer local` lalu `composer share:alt`).
- Cloudflare Pages/Workers, Netlify, dan Vercel **tidak bisa** menjalankan Laravel (PHP) dengan unggahan dan queue.
- Untuk launch produksi: VPS (rencana Hostinger) dengan MySQL. Skrip lama di [setup-server.sh](setup-server.sh) masih untuk dua situs demo/QA di Oracle/Google dan perlu disesuaikan sebelum dipakai.
