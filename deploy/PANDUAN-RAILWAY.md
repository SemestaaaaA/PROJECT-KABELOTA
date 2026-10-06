# Panduan Railway: link demo dan link QA yang selalu hidup

Railway menjalankan Kabelota dari repo GitHub. Setiap kali branch di-push, Railway membangun ulang dan memperbaruinya sendiri. Kita buat dua service dalam satu project:

| Service | Branch | Untuk | Email |
|---|---|---|---|
| `kabelota-demo` | `demo` | Klien dan orang luar | Hanya dicatat di log |
| `kabelota-qa` | `qa` | Anggota tim QA, pita kuning "Versi QA" | Email asli lewat Brevo |

Biaya:
- **Trial:** kredit US$5 untuk 30 hari, tanpa kartu. Cukup untuk dua service kecil selama masa QA.
- **Free:** setelah trial, kreditnya US$1/bulan. Tidak cukup untuk dua service yang menyala terus.
- **Hobby:** US$5/bulan (sekitar Rp85rb) dan perlu kartu. Jenius e-card biasanya diterima Railway. Biaya ini bisa ditagihkan ke klien sebagai biaya infrastruktur.

Pantau pemakaian di menu **Usage**.

Yang **tidak** bisa: Railway memblokir SMTP di paket Trial, Free, dan Hobby. Karena itu email QA dikirim lewat API Brevo (sudah disiapkan di kode).

## Mode gratis (tanpa kartu)

| Tahap | Kredit | Batas per service | Catatan |
|---|---|---|---|
| Trial, 30 hari pertama | US$5 sekali | 1 GB RAM, volume 500 MB, maks. 5 service per project | Daftar pakai GitHub, tanpa kartu |
| Free, setelah trial | US$1 per bulan (tidak menumpuk) | 0,5 GB RAM, volume 500 MB | Otomatis pindah ke Free saat trial habis |

Supaya tetap gratis:

1. **Nyalakan Serverless di setiap service:** Service → Settings → Deploy → **Serverless** → aktifkan, lalu **Redeploy** (setelan baru berlaku setelah deploy ulang).
   - Service tidur setelah sekitar 5-10 menit tanpa pengunjung, dan selama tidur tidak memakan kredit.
   - Kunjungan pertama setelah tidur butuh 10-30 detik. Kadang muncul **502**; muat ulang halaman.
   - Kabelota memakai SQLite di volume, jadi tidak ada koneksi keluar yang membuatnya tetap terjaga.
2. **Satu service dulu.** Kredit US$1 cukup untuk satu service yang sering tidur, tapi pas-pasan untuk dua.
   - Selama masa QA, nyalakan `kabelota-qa`.
   - Service demo dinyalakan hanya menjelang presentasi ke klien.
   - Untuk mematikan service: klik service → **Settings → Danger → Remove** (data volume ikut hilang), atau cukup biarkan Serverless menidurkannya.
3. **Pantau Usage** seminggu sekali. Kalau angka "Estimated" mendekati kredit, matikan service yang tidak dipakai.
4. **Unduh backup sebelum trial habis.** Railway menghapus volume buatan akun Trial 30 hari setelah kredit trial habis. Dari laptop:

   ```bash
   railway ssh --service kabelota-qa
   ```

   Di dalam server:

   ```bash
   php artisan backup:run && ls storage/app/backups/Kabelota
   ```

   Untuk QA, kehilangan data uji tidak masalah. Untuk data asli (pilot), pindah ke Hobby.
5. **Yang tidak jalan saat tidur:** jadwal otomatis (tutup lowongan kedaluwarsa, backup harian, pengingat SKK). Semuanya jalan lagi saat ada pengunjung. Untuk demo dan QA ini tidak masalah.

Kapan harus Hobby (US$5/bulan): saat pilot dengan alumni dan perusahaan sungguhan, karena data harus aman, server tidak boleh tidur, dan jadwal otomatis harus jalan.

---

## 1. Buat akun dan project (±10 menit)

1. Buka **railway.com** → Login → **Login with GitHub** (pakai akun pemilik repo PROJECT-KABELOTA).
2. **New Project** → **Deploy from GitHub repo** → **Configure GitHub App** → izinkan repo `PROJECT-KABELOTA` → pilih repo itu.
3. Railway langsung mencoba deploy, dan percobaan pertama akan gagal karena variabel belum diisi. Itu normal.
4. Klik service yang muncul → **Settings**:
   - **Service Name:** `kabelota-demo`
   - **Source → Branch:** `demo`

## 2. Pasang volume (tempat foto, CV, dan database)

Tanpa volume, semua unggahan hilang setiap deploy.

Di kanvas project, klik kanan service `kabelota-demo` → **Attach volume**. Mount path: `/data`.

## 3. Isi variabel

1. Di laptop, buat kunci aplikasi:

   ```bash
   cd ~/Kuliahan/Penting/Kabelota/app && php artisan key:generate --show
   ```

   Salin hasilnya (diawali `base64:`).
2. Service → **Variables** → **Raw Editor**. Tempel isi file [railway/variables.demo.env](railway/variables.demo.env), lalu ganti:
   - `APP_KEY` = hasil langkah 1;
   - `KABELOTA_ADMIN_PASSWORD` dan `KABELOTA_DEMO_PASSWORD` = sandi baru, minimal 12 karakter. Jangan pakai sandi pribadimu;
   - `KABELOTA_OPS_EMAIL` = emailmu (menerima pemberitahuan kalau backup atau email gagal).
3. **Update Variables**.

## 4. Buka ke internet

1. Service → **Settings → Networking → Generate Domain**. Isi port `8080` kalau ditanya.
2. Railway akan deploy ulang. Buka tab **Deployments → View logs**. Deploy pertama 3-6 menit karena harus membangun aset dan mengisi data contoh.
3. Kalau status **Active**, buka domain `kabelota-demo-xxxx.up.railway.app` dari HP. Ini link demo yang bisa disebar. Link ini tidak berubah walau server restart.

## 5. Service QA

Di project yang sama: **New** → **GitHub Repo** → repo yang sama, lalu ulangi langkah 1.4 sampai 4 dengan isian berikut:

| Isian | Nilai |
|---|---|
| Service Name | `kabelota-qa` |
| Branch | `qa` |
| Volume | `/data` (volume baru, terpisah dari demo) |
| Variables | isi [railway/variables.qa.env](railway/variables.qa.env), dengan **APP_KEY baru** (jalankan `key:generate --show` lagi) |

## 6. Email QA lewat Brevo (gratis 300 email/hari)

1. Daftar di **brevo.com**.
2. **Senders, Domains & Dedicated IPs → Senders → Add a sender:** pakai Gmail-mu, lalu klik link verifikasi yang dikirim ke Gmail itu.
3. **SMTP & API → API Keys → Generate a new API key**. Salin kuncinya (diawali `xkeysib-`).
4. Di service `kabelota-qa` → Variables:
   - `BREVO_API_KEY` = kunci tadi;
   - `MAIL_FROM_ADDRESS` = Gmail yang sudah diverifikasi.
5. Tes: daftar akun baru di link QA. Email verifikasi harus masuk (cek folder spam juga).

## 7. Google Form QA

Isi `KABELOTA_QA_FORM_URL` di Variables `kabelota-qa` dengan link Google Form. Tombol "Laporkan masalah" akan muncul di pita kuning.

---

## Pemakaian sehari-hari

**Rilis QA baru.** Railway otomatis deploy setiap kali branch `qa` berubah. Dari folder `app`:

```bash
git push origin main:qa
```

Lalu umumkan di grup: nomor rilis + daftar perubahan.

**Perbaikan darurat di demo** (jarang). Perbaiki di `main`, lalu dari folder `app-demo`:

```bash
git cherry-pick <kode-commit> && git push origin demo
```

**Menjadikan anggota QA admin.** Pasang Railway CLI sekali:

```bash
npm i -g @railway/cli
```

Lalu dari folder `app`:

```bash
railway login && railway link && railway ssh --service kabelota-qa
```

Di dalam server:

```bash
php artisan kabelota:make-admin email@anggota.com
```

**Reset data QA** (hapus semua akun uji). Dari `railway ssh`:

```bash
php artisan kabelota:reset-demo --force
```

## Kalau ada masalah

| Gejala | Coba |
|---|---|
| Deploy gagal di tahap build | Deployments → View logs → Build Logs; kirim 20 baris terakhir ke Nakita/Claude |
| "Application failed to respond" | Cek port di Networking = `8080`; cek `APP_KEY` sudah diisi |
| Error 500 | Deploy Logs; biasanya `APP_KEY` atau sandi seeder kosong |
| Unggahan hilang setelah deploy | Volume belum terpasang di `/data` |
| Email tidak masuk | `MAIL_MAILER=brevo`, `BREVO_API_KEY` benar, `MAIL_FROM_ADDRESS` = sender yang sudah diverifikasi |
| Kredit habis | Menu Usage. Upgrade ke Hobby, atau matikan service demo saat tidak dipakai |

## Catatan

- Link `trycloudflare` (laptop) tetap bisa dipakai sebagai cadangan.
- Cloudflare Pages/Workers **tidak bisa** menjalankan Laravel (PHP). Cloudflare hanya dipakai untuk tunnel atau DNS domain nanti.
- Vercel juga tidak cocok: file unggahan hilang, tidak ada queue, dan database harus pindah ke layanan lain.
- Untuk launch produksi, skrip VPS di [deploy/setup-server.sh](setup-server.sh) tetap bisa dipakai, atau lanjut di Railway Hobby/Pro dengan MySQL.
