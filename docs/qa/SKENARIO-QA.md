# Skenario uji Kabelota

Daftar tugas untuk anggota QA. Kerjakan sesuai peran yang dibagikan. Setiap kali ada yang aneh, isi Google Form "Laporan QA Kabelota" (satu form untuk satu temuan).

Server QA ditandai pita kuning **Versi QA** di atas. Jangan uji di link demo klien.

**Aturan umum**
- Uji di HP dulu, lalu laptop.
- Coba di mode terang dan gelap (ikon bulan/matahari, atau di menu ☰ di HP).
- Pakai email asli yang bisa kamu buka (untuk verifikasi email).
- Semua data di server QA boleh diacak-acak; server bisa direset kapan saja.

---

## A. Pengunjung (belum masuk)

| No | Tugas | Yang seharusnya terjadi |
|---|---|---|
| A1 | Buka beranda, gulir sampai bawah | Semua gambar tampil, tidak ada teks terpotong, tidak bisa digeser ke samping |
| A2 | Di HP, buka menu ☰ lalu pindah-pindah halaman | Menu terbuka/tertutup dengan benar, halaman aktif ditandai |
| A3 | Cari talenta dengan kata kunci "jalan" | Hasil muncul, jumlah hasil masuk akal |
| A4 | Pakai filter jenjang, pengalaman, lokasi; ganti Grid/List | Hasil berubah sesuai filter, badge jumlah filter benar |
| A5 | Buka salah satu profil talenta | Kontak (email/WA) **tidak** terlihat |
| A6 | Klik "Ajukan Rekrut" tanpa masuk | Diminta masuk dulu |
| A7 | Buka Lowongan, buka detail salah satu lowongan, buka halaman perusahaannya | Semua halaman terbuka, data cocok |
| A8 | Kirim pesan di halaman Kontak | Muncul pesan berhasil |
| A9 | Buka alamat asal-asalan, mis. `/abc123` | Halaman 404 Kabelota, ada tombol kembali ke beranda |

## B. Talenta (alumni atau mahasiswa)

| No | Tugas | Yang seharusnya terjadi |
|---|---|---|
| B1 | Daftar akun baru pakai email asli | Diarahkan ke halaman verifikasi, email verifikasi masuk (cek folder spam juga) |
| B2 | Klik link verifikasi di email | Akun aktif, masuk ke Buat Profil |
| B3 | Isi profil 4 langkah sampai selesai, unggah foto (≤5 MB) dan CV PDF (≤3 MB) | Bisa lanjut tiap langkah, pratinjau kartu ikut berubah, profil tersimpan |
| B4 | Coba unggah file terlalu besar atau bukan PDF | Muncul pesan error yang jelas, data lain tidak hilang |
| B5 | Buka profil sendiri, lihat CV lewat pratinjau | PDF terbuka di halaman, tombol unduh jalan |
| B6 | Ubah status ketersediaan (Tersedia / Terikat kontrak / Tidak tersedia) | Status berubah di profil dan di kartu pencarian; saat Tidak tersedia, perusahaan tidak bisa mengirim tawaran |
| B7 | Lamar satu lowongan | Muncul di "Lamaran Saya" dengan status Baru |
| B8 | Terima atau tolak tawaran rekrut (minta tim Perusahaan mengirim tawaran ke kamu) | Status tawaran berubah; kalau diterima, perusahaan bisa melihat kontakmu |
| B9 | Keluar, lalu Lupa kata sandi | Email reset masuk, sandi baru bisa dipakai |
| B10 | Coba buka `/perusahaan/tawaran` | Dikembalikan ke beranda dengan pesan "Halaman ini khusus akun perusahaan", bukan error |

## C. Perusahaan (HRD)

| No | Tugas | Yang seharusnya terjadi |
|---|---|---|
| C1 | Daftar lewat "Mewakili perusahaan?" | Akun perusahaan dibuat, diminta melengkapi profil perusahaan |
| C2 | Lengkapi profil perusahaan + logo + dokumen NIB/SBU | Tersimpan, status "Menunggu verifikasi" |
| C3 | Sebelum diverifikasi, coba Ajukan Rekrut | Diarahkan ke profil perusahaan dengan pesan bahwa fitur ini terbuka setelah diverifikasi |
| C4 | Setelah admin memverifikasi: Ajukan Rekrut ke talenta (anggota tim B) | Muncul di "Tawaran Terkirim"; talenta menerima email |
| C5 | Pasang lowongan, pilih paket, unggah bukti transfer | Status "Menunggu persetujuan" |
| C6 | Setelah admin menyetujui: cek lowongan tayang di /lowongan | Lowongan muncul |
| C7 | Buka Pelamar, ubah status lamaran ke Ditinjau lalu Diterima | Talenta menerima email; kontak talenta terlihat setelah Diterima |

## D. Admin (hanya anggota yang ditunjuk)

| No | Tugas | Yang seharusnya terjadi |
|---|---|---|
| D1 | Masuk ke `/admin` | Dasbor tampil dengan angka |
| D2 | Verifikasi satu perusahaan, tolak satu dengan alasan | Perusahaan menerima email, status berubah di web |
| D3 | Setujui satu lowongan setelah melihat bukti transfer | Lowongan tayang |
| D4 | Buka Pesan Kontak | Pesan dari A8 ada |
| D5 | Buka dokumen talenta (CV/SKK) dari panel | PDF terbuka |

## E. Uji bebas (15 menit)

Pakai web seperti pengguna biasa tanpa skenario. Coba hal-hal "nakal": klik tombol dua kali cepat, tekan Kembali di tengah formulir, isi kolom dengan teks sangat panjang, ganti ukuran layar, putar HP ke landscape.
