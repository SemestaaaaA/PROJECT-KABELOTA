# Perintah untuk agent Railway

Tempel isi blok di bawah ke chat agent Railway (di dashboard project `supportive-endurance`). Bagian bertanda **PERLU INPUT MANUAL** harus kamu isi sendiri; agent diminta berhenti dan bertanya di titik itu.

```text
Kamu membantu merapikan deployment Laravel "Kabelota" (service PROJECT-KABELOTA, repo SemestaaaaA/PROJECT-KABELOTA, branch main) di project ini. Ada service MySQL (mysql:8) dan volume. Ini server QA gratis, jadi hemat kredit adalah prioritas.

ATURAN
- Kerjakan berurutan dan laporkan hasil tiap langkah dengan singkat.
- Minta izin saya sebelum mengubah, menghapus, atau me-redeploy apa pun yang tidak tertulis di bawah.
- JANGAN menghapus service atau volume, JANGAN mengubah isi/data MySQL, dan JANGAN menampilkan nilai rahasia (APP_KEY, sandi, API key). Sebut nama variabelnya saja.
- Kalau sebuah langkah butuh nilai yang hanya saya punya, tulis "PERLU INPUT MANUAL: <apa yang dibutuhkan dan di mana saya mengisinya>", lalu berhenti dan tunggu saya.

LANGKAH
1. Volume: terapkan perubahan yang masih staged (data-volume, mount path /data, service PROJECT-KABELOTA), lalu redeploy PROJECT-KABELOTA. Setelah selesai, konfirmasi volume benar-benar ter-mount di /data.

2. Cek variabel PROJECT-KABELOTA (hanya laporkan ada/tidak dan nilai yang bukan rahasia):
   - APP_ENV=staging, APP_DEBUG=false, APP_KEY terisi, LOG_CHANNEL=stderr
   - APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}} (atau domain publiknya, harus https)
   - SESSION_DRIVER=database, SESSION_SECURE_COOKIE=true, CACHE_STORE=database, QUEUE_CONNECTION=database
   - Database MySQL: DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD harus merujuk ke service MySQL lewat reference variable (mis. ${{MySQL.MYSQLHOST}}, ${{MySQL.MYSQLPORT}}, ${{MySQL.MYSQLDATABASE}}, ${{MySQL.MYSQLUSER}}, ${{MySQL.MYSQLPASSWORD}}). Pakai host internal (private network), bukan host publik.
   - KABELOTA_QUICK_LOGIN=true, KABELOTA_ADMIN_PASSWORD terisi (minimal 12 karakter), KABELOTA_OPS_EMAIL terisi
   - MAIL_MAILER=brevo, MAIL_FROM_ADDRESS terisi
   Kalau ada yang hilang atau salah, usulkan perbaikannya dan minta persetujuan saya sebelum menyimpan.

3. Memori MySQL: start command MySQL memakai --innodb-buffer-pool-size=1G. Setelah masa trial berakhir, batas RAM paket Free hanya 0,5 GB, sehingga MySQL bisa kena OOM. Cek pemakaian memori MySQL sekarang, lalu usulkan nilai yang lebih kecil (mis. 128M) dan minta persetujuan saya sebelum mengubahnya.

4. Hemat kredit: aktifkan Serverless (app sleeping) pada service PROJECT-KABELOTA saja, lalu redeploy supaya berlaku. Jangan ubah service MySQL. Setelah itu tampilkan estimasi biaya bulanan dari menu Usage untuk kedua service dan sisa kredit.

5. Email: PERLU INPUT MANUAL: BREVO_API_KEY. Saya akan membuat kuncinya sendiri di brevo.com (SMTP & API -> API Keys) dan memasukkannya sendiri di Variables PROJECT-KABELOTA. Jangan minta saya menempelkan kuncinya ke chat. Setelah saya bilang sudah diisi, redeploy dan cek di log bahwa tidak ada error mail.

6. Verifikasi akhir. Buka domain publik dan laporkan status HTTP untuk: /, /up, /talenta, /lowongan, /admin/login. Lalu cek log deploy terbaru: harus tidak ada error migrasi, seeder, atau koneksi database. Kalau ada, tampilkan 20 baris log yang relevan.

7. Ringkasan akhir: daftar apa yang sudah diubah, apa yang masih menunggu saya, domain publik, perkiraan biaya per bulan, dan risiko yang tersisa.
```

## Yang harus kamu kerjakan sendiri (manual)

| Kapan | Apa | Di mana |
|---|---|---|
| Langkah 2, kalau ada variabel hilang | Menyetujui perubahan variabel | Chat agent |
| Langkah 3 | Menyetujui perubahan memori MySQL | Chat agent |
| Langkah 5 | Daftar Brevo, verifikasi sender (Gmail), buat API key, isi `BREVO_API_KEY` di Variables | brevo.com dan Railway Variables |
| Setelah selesai | Login admin `admin@kabelota.id` dan aktifkan 2FA (authenticator) | `/admin` |
| Opsional | Buat Google Form dari [docs/qa/ISI-GFORM.md](../../docs/qa/ISI-GFORM.md) | forms.google.com |
