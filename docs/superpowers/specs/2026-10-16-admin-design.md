# Spec: Admin (proyek 4 dari 5, dipecah 4a / 4b / 4c)

## Context
Dashboard admin berisi 12 widget yang ditumpuk vertikal, tanpa notifikasi di dalam panel (admin hanya menerima email). Halaman resource hanya punya filter status dan tidak ada ringkasan angka. Profil admin hanya nama, email, sandi, 2FA.

## Keputusan pengguna
- Lonceng notifikasi sungguhan (database notifications Filament) + kartu Notifikasi di bento.
- Urutan bento: Perlu tindakan · Notifikasi · Pesan masuk · Ringkasan 30 hari · Alur rekrutmen · Pendaftaran per minggu · Pendapatan per bulan · Lowongan berakhir 14 hari. Sisanya (Perusahaan teraktif, Konsentrasi, Jenjang, Domisili, Kualitas data, Pengunjung) di bagian "Analitik lainnya" yang bisa dibuka-tutup, tertutup secara bawaan.
- Profil admin: preferensi notifikasi, sesi aktif, riwayat aktivitas admin, foto profil.
- Dikerjakan berurutan: 4a → 4b → 4c.

## 4a. Dashboard, tema, notifikasi
- **Tema Filament** (`resources/css/filament/admin/theme.css`, lewat Vite + `->viteTheme()`): font Barlow (teks), Barlow Condensed (angka besar), IBM Plex Mono (data); warna utama `#F5B800`, aksen `#FF7A1A`; kartu radius 12px, bayangan halus; kelas bento.
- **Lonceng**: `->databaseNotifications()` + polling 30 dtk, tabel `notifications`. `App\Support\AdminAlerts::send($event, $title, $body, $url)` mengirim ke semua admin (4c menambah filter preferensi). Kejadian:
  - `company_verification` — perusahaan mengunggah NIB/SBU atau mengajukan ulang setelah ditolak.
  - `payment_proof` — lowongan baru dengan bukti bayar.
  - `skk_review` — data/scan SKK talenta berubah dan perlu dicek.
  - `contact_message` — pesan kontak masuk.
  - `job_failed` — job antrean gagal (dibatasi sekali per jenis per jam, sama seperti email).
  Setiap notifikasi punya tombol "Buka" ke halaman resource terkait dan ditandai dibaca saat diklik.
- **Pesan kontak**: kolom `read_at`; belum dibaca tampil tebal + badge jumlah di navigasi; dibuka (modal Lihat) = dibaca; aksi "Tandai dibaca".
- **Ringkasan (bento)**: grid 4 kolom (2 di tablet, 1 di HP).
  - Baris 1: Perlu tindakan (2 kolom) · Notifikasi (1) · Pesan masuk (1), tinggi sama.
  - Baris 2: Ringkasan 30 hari (penuh).
  - Baris 3: Alur rekrutmen (2) · Pendaftaran per minggu (2).
  - Baris 4: Pendapatan per bulan (2) · Lowongan berakhir 14 hari (2).
  - Section "Analitik lainnya" (collapsible, collapsed): 6 widget lain, lazy.
  - Widget baru: `AdminNotificationsWidget` (5 terbaru, titik belum dibaca, "Tandai semua dibaca", kosong: "Belum ada notifikasi"), `InboxWidget` (5 pesan terbaru, belum dibaca ditandai, tautan ke Pesan Kontak, kosong: "Belum ada pesan").

## 4b. Halaman resource
| Resource | Filter baru | Kartu statistik (header widget) |
|---|---|---|
| Perusahaan | kota, jenis, dokumen NIB/SBU ada, punya lowongan tayang, rentang tanggal daftar | Total · Menunggu verifikasi · Terverifikasi · Lowongan tayang |
| Lowongan | paket, konsentrasi, lokasi, sedang tayang, bukti bayar menunggu, rentang tanggal | Menunggu cek bayar · Tayang · Pendapatan bulan ini · Total pelamar |
| Talenta | jenjang SKK, konsentrasi, ketersediaan, domisili, SKK kedaluwarsa, profil tersembunyi, punya CV | Total (alumni/mahasiswa) · Tersedia · SKK perlu dicek · Rata-rata kelengkapan |
| Lamaran | perusahaan, lowongan | Lamaran 30 hari · Baru belum ditinjau · Tawaran kerja berjalan · Diambil |
| Tawaran rekrut | perusahaan | Terkirim · Tertarik · Tawaran kerja · Diambil |

Lamaran: kolom alasan tolak + batas jawab; aksi "Detail" (pesan pelamar, catatan perusahaan, posisi/tanggal tawaran kerja, pesan perpisahan). Tawaran rekrut: aksi kepala "? Apa ini?" (modal penjelasan alur dan bedanya dengan Lamaran) + tooltip status.

## 4c. Profil admin
- Preferensi notifikasi per kejadian 4a: lonceng saja / lonceng + email / mati (kolom JSON `users.notification_prefs`, default lonceng aktif). `AdminAlerts::send` menghormatinya.
- Sesi aktif: daftar sesi dari tabel `sessions` (perangkat dari user agent, IP, terakhir aktif) + "Keluar dari perangkat lain" (konfirmasi sandi).
- Riwayat aktivitas admin: tabel `admin_activities` (admin, aksi, subjek, catatan, waktu) diisi oleh aksi verifikasi perusahaan, setujui/tolak lowongan, cek SKK; halaman "Riwayat aktivitas" di admin + 10 terakhir di profil.
- Foto profil: unggah avatar (re-encode lewat `ImageStore`), dipakai di topbar Filament (`HasAvatar`).

## Pengujian
Tiap sub-proyek punya file tes sendiri (`AdminAlertsTest`, `AdminDashboardTest` diperluas, `AdminResourcesTest`, `AdminProfileTest`). Semua tes lama tetap lulus.
