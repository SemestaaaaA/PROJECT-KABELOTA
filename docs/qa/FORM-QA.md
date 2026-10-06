# Google Form: Laporan QA Kabelota

Salin pertanyaan di bawah ke Google Forms (forms.google.com → Kosong). Setelah jadi:

1. **Setelan → Respons:** aktifkan "Kumpulkan alamat email" (supaya tahu siapa pelapor kalau perlu tanya balik).
2. **Respons → Tautkan ke Spreadsheet:** buat sheet baru, lalu tambah kolom rekap (bagian paling bawah).
3. Salin link form, isi ke `KABELOTA_QA_FORM_URL` di `.env` server QA, lalu jalankan `php artisan optimize`. Tombol "Laporkan masalah" di pita kuning akan muncul.

Judul form: **Laporan QA Kabelota**
Deskripsi form:
> Satu form untuk satu temuan. Kalau menemukan 3 masalah, isi 3 kali. Sebelum mengisi, coba ulangi langkahnya sekali lagi supaya yakin masalahnya bisa terjadi lagi.

---

## Bagian 1: Penguji

| No | Pertanyaan | Jenis | Pilihan | Wajib |
|---|---|---|---|---|
| 1 | Nama | Jawaban singkat | | Ya |
| 2 | Kamu menguji sebagai | Pilihan ganda | Pengunjung (belum masuk) · Talenta alumni · Talenta mahasiswa · Perusahaan (HRD) · Admin | Ya |
| 3 | Perangkat | Pilihan ganda | HP Android · iPhone · Laptop/PC · Tablet | Ya |
| 4 | Browser | Pilihan ganda | Chrome · Safari · Firefox · Lainnya | Ya |
| 5 | Tema saat menguji | Pilihan ganda | Terang · Gelap | Ya |

## Bagian 2: Temuan

| No | Pertanyaan | Jenis | Pilihan / petunjuk | Wajib |
|---|---|---|---|---|
| 6 | Jenis temuan | Pilihan ganda | Error atau tidak bisa lanjut · Tampilan berantakan · Teks salah atau membingungkan · Lambat · Saran fitur | Ya |
| 7 | Halaman atau fitur | Drop-down | Beranda · Cari Talenta · Profil talenta · Buat/Ubah Profil · Tawaran · Lamaran · Lowongan · Pasang Lowongan · Profil Perusahaan · Pelamar · Daftar/Masuk · Lupa kata sandi · Verifikasi email · Tentang Kami/Kontak · Panel Admin · Lainnya | Ya |
| 8 | Link halaman | Jawaban singkat | Petunjuk: salin dari address bar browser | Tidak |
| 9 | Judul singkat masalah | Jawaban singkat | Contoh: "Tombol Simpan tidak merespons di langkah 3" | Ya |
| 10 | Langkah yang kamu lakukan | Paragraf | Petunjuk: tulis berurutan. 1. Buka … 2. Klik … 3. Isi … | Ya |
| 11 | Yang kamu harapkan terjadi | Paragraf | | Ya |
| 12 | Yang benar-benar terjadi | Paragraf | Tulis juga pesan error yang muncul, kalau ada | Ya |
| 13 | Seberapa sering terjadi | Pilihan ganda | Selalu · Kadang-kadang · Sekali saja | Ya |
| 14 | Tingkat keparahan | Pilihan ganda | lihat tabel di bawah | Ya |
| 15 | Screenshot atau rekaman layar | Upload file | Maks 5 file, 10 MB. Gambar atau video | Tidak |

Pilihan untuk pertanyaan 14 (tulis lengkap dengan penjelasannya supaya penguji tidak bingung):

| Pilihan | Artinya |
|---|---|
| Kritis | Data orang lain terlihat, atau sama sekali tidak bisa lanjut |
| Tinggi | Fitur utama gagal, tapi ada cara lain untuk menyelesaikannya |
| Sedang | Mengganggu, tapi fitur tetap bisa dipakai |
| Rendah | Kosmetik: salah ketik, warna, jarak, ikon |

Catatan: pertanyaan Upload file mewajibkan penguji masuk akun Google.

---

## Kolom rekap di Google Sheets

Tambahkan di kanan kolom jawaban. Diisi oleh Nakita atau QA lead, bukan oleh penguji.

| Kolom | Isi |
|---|---|
| ID | QA-001, QA-002, … |
| Status | Baru · Dikerjakan · Selesai · Bukan bug · Ditunda (pakai Validasi data → Drop-down) |
| PIC | Siapa yang memperbaiki |
| Rilis QA | Nomor rilis tempat temuan muncul, mis. QA-1 |
| Catatan perbaikan | Ringkasan perbaikan + kode commit |

Tips: Format → Pemformatan bersyarat, warnai baris "Kritis" merah dan "Tinggi" oranye.

## Ritme QA

1. Nakita mengumumkan rilis QA baru di grup: nomor rilis, link server QA, daftar yang berubah.
2. Anggota menguji 2–3 hari mengikuti [SKENARIO-QA.md](SKENARIO-QA.md), lalu mengisi form untuk tiap temuan.
3. Triase: Kritis dan Tinggi dikerjakan dulu. Rendah boleh ditumpuk ke rilis berikutnya.
4. Setelah diperbaiki dan dirilis ulang, pelapor mengecek lagi lalu status diubah ke Selesai.
