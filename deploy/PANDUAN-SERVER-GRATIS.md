# Panduan server gratis: link demo dan link QA yang selalu hidup

Hasil akhirnya dua link yang tetap hidup walau laptop mati:

| Link | Branch | Untuk siapa | Berubah kapan |
|---|---|---|---|
| `https://demo.<IP>.sslip.io` | `demo` | Klien dan orang luar | Hampir tidak pernah (dibekukan) |
| `https://qa.<IP>.sslip.io` | `qa` | Anggota tim QA | Setiap ada rilis QA baru |

`<IP>` adalah alamat server dengan titik diganti strip, mis. `152-67-10-20`. `sslip.io` memberi nama domain gratis dari IP, jadi belum perlu beli domain. HTTPS dipasang otomatis.

---

## Bagian 1: Buat server di Oracle Cloud (sekali, ±30 menit)

1. Buka **oracle.com/cloud/free** → Start for free.
2. Isi data. **Home Region: Singapore** (tidak bisa diganti nanti).
3. Verifikasi kartu: pakai Jenius e-card (Visa debit). Sebelumnya di aplikasi Jenius:
   - pastikan transaksi online dan luar negeri aktif;
   - isi saldo sekitar Rp50rb. Oracle menahan kira-kira US$1 untuk verifikasi, lalu dikembalikan.

   Kalau kartu ditolak, coba nomor kartu fisik Jenius. Kalau tetap ditolak, pakai Google Cloud (Bagian 5).
4. Setelah masuk konsol: **Create a VM instance**.
   - Image: **Canonical Ubuntu 24.04**.
   - Shape: **Ampere (VM.Standard.A1.Flex)**, 2 OCPU, 12 GB RAM. Masih gratis selamanya.
   - SSH keys: pilih **Generate a key pair**, lalu **Save private key**. Simpan file `.key` itu baik-baik, misalnya di `~/.ssh/kabelota-oracle.key`.
   - Create. Tunggu status RUNNING, lalu catat **Public IP address**.

   Kalau muncul "Out of capacity", coba lagi beberapa jam kemudian atau pilih 1 OCPU / 6 GB.
5. Buka port web: halaman instance → Subnet → **Default Security List** → Add Ingress Rules:
   - Source CIDR `0.0.0.0/0`, Destination Port `80`
   - lalu satu lagi dengan Destination Port `443`.

## Bagian 2: Masuk ke server dari Mac

Di Terminal Mac:

```bash
chmod 600 ~/.ssh/kabelota-oracle.key
ssh -i ~/.ssh/kabelota-oracle.key ubuntu@IP-SERVER
```

Ketik `yes` saat ditanya. Kalau prompt berubah menjadi `ubuntu@...`, kamu sudah di dalam server.

## Bagian 3: Beri server izin membaca repo GitHub

Di server:

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
```

Salin baris yang muncul. Di GitHub: repo PROJECT-KABELOTA → **Settings → Deploy keys → Add deploy key**. Tempel, beri judul "server-oracle", **jangan** centang write access. Lalu tes:

```bash
ssh -T git@github.com
```

Ketik `yes`. Pesan "successfully authenticated" berarti berhasil.

## Bagian 4: Pasang Kabelota (±15 menit, sebagian besar menunggu)

```bash
curl -fsSL https://raw.githubusercontent.com/SemestaaaaA/PROJECT-KABELOTA/main/deploy/setup-server.sh -o setup-server.sh
sudo bash setup-server.sh
```

Repo privat? Pakai cara ini sebagai gantinya:

```bash
git clone git@github.com:SemestaaaaA/PROJECT-KABELOTA.git /tmp/kab && sudo bash /tmp/kab/deploy/setup-server.sh
```

Setelah selesai, jalankan dua perintah yang ditampilkan di akhir:

```bash
bash /var/www/kabelota-demo/deploy/deploy.sh demo --fresh
bash /var/www/kabelota-qa/deploy/deploy.sh qa --fresh
```

Buka kedua link dari HP. Sandi akun admin dan HRD contoh dibuat acak; lihat dengan:

```bash
grep PASSWORD /var/www/kabelota-demo/.env /var/www/kabelota-qa/.env
```

## Bagian 5: Cadangan, Google Cloud e2-micro

Kalau Oracle menolak kartu: console.cloud.google.com → Compute Engine → Create instance.

| Pengaturan | Nilai |
|---|---|
| Region | `us-west1`, `us-central1`, atau `us-east1` (hanya region ini yang gratis) |
| Machine | `e2-micro` |
| Boot disk | Ubuntu 24.04, Standard persistent disk 30 GB |
| Firewall | centang Allow HTTP dan HTTPS |

Server ini hanya 1 GB RAM, jadi tambahkan swap dulu sebelum Bagian 4:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Langkah lain sama, ganti `ubuntu@` dengan username Google kamu.

---

## Pemakaian sehari-hari

**Rilis QA baru** (setelah fitur di `main` siap diuji):

Di Mac:

```bash
git checkout qa && git merge main && git push && git checkout main
```

Lalu di server:

```bash
bash /var/www/kabelota-qa/deploy/deploy.sh qa
```

Umumkan di grup: nomor rilis + daftar perubahan.

**Reset data QA** (hapus semua akun uji, isi ulang data contoh):

```bash
bash /var/www/kabelota-qa/deploy/deploy.sh qa --fresh
```

**Perbaikan darurat di demo** (jarang): perbaiki di `main` dulu, lalu di Mac:

```bash
git checkout demo && git cherry-pick <kode-commit> && git push && git checkout main
```

Kemudian di server:

```bash
bash /var/www/kabelota-demo/deploy/deploy.sh demo
```

**Menjadikan anggota QA admin** (setelah dia daftar di server QA):

```bash
cd /var/www/kabelota-qa && php artisan kabelota:make-admin email@anggota.com
```

**Lihat error:**

```bash
tail -50 /var/www/kabelota-qa/storage/logs/laravel-*.log
```

## Kalau ada masalah

| Gejala | Coba |
|---|---|
| Link tidak bisa dibuka sama sekali | Cek port 80/443 di Security List (Bagian 1 langkah 5); `sudo systemctl status caddy` |
| Halaman error 500 | Lihat log (perintah di atas); biasanya izin folder, jalankan ulang `deploy.sh` |
| Email tidak terkirim | Di QA isi kredensial Brevo di `.env`, `MAIL_MAILER=smtp`, lalu `php artisan optimize` |
| Unggah file gagal | `sudo systemctl restart php8.4-fpm`; batas sudah 8 MB per file |
| Notifikasi tidak terkirim | `sudo supervisorctl status`; harus RUNNING |

## Laptop saja (kalau belum punya server)

Folder `app-demo` di sebelah folder `app` berisi branch `demo` dengan database sendiri. Pengerjaan di `app` tidak mempengaruhinya.

Terminal tab 1:

```bash
cd ~/Kuliahan/Penting/Kabelota/app-demo && composer demo
```

Terminal tab 2:

```bash
cd ~/Kuliahan/Penting/Kabelota/app-demo && composer share:alt
```

Server pengerjaan (`composer dev` di folder `app`) jalan di port 8001, jadi keduanya bisa menyala bersamaan.
