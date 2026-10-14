# Isi Google Form: Laporan QA Kabelota

Salin ke forms.google.com → Form kosong. Server QA: **https://project-kabelota-production-ba0a.up.railway.app** (ganti kalau domainnya berubah).

Pengaturan form:
- **Setelan → Respons:** aktifkan "Kumpulkan alamat email" dan "Kirim salinan respons ke responden".
- **Respons → Tautkan ke Spreadsheet** (kolom rekap ada di bagian paling bawah).
- Kolom unggah file mewajibkan penguji masuk akun Google. Kalau tidak mau, hapus pertanyaan 14 dan minta screenshot dikirim ke grup.

---

## Judul dan deskripsi form

**Judul:** Laporan QA Kabelota

**Deskripsi:**
> Terima kasih sudah ikut menguji Kabelota, platform talenta Teknik Sipil UNTAD.
>
> Satu form untuk satu temuan. Kalau menemukan 3 masalah, isi 3 kali. Sebelum mengisi, ulangi langkahnya sekali lagi supaya yakin masalahnya memang bisa terjadi lagi.
>
> Link server uji coba: https://project-kabelota-production-ba0a.up.railway.app
> Data di server ini boleh diacak-acak dan bisa direset kapan saja. Jangan unggah dokumen asli yang sensitif (KTP, ijazah asli).

---

## Bagian 1: Tentang kamu

| No | Pertanyaan | Jenis | Pilihan atau petunjuk | Wajib |
|---|---|---|---|---|
| 1 | Nama | Jawaban singkat | | Ya |
| 2 | Kamu menguji sebagai | Pilihan ganda | Pengunjung (belum masuk) · Talenta alumni · Talenta mahasiswa · Perusahaan (HRD) · Admin | Ya |
| 3 | Cara masuk yang kamu pakai | Pilihan ganda | Daftar dengan email sendiri · Tombol "HRD Perusahaan" · Tombol "Talenta" · Tidak masuk | Ya |
| 4 | Perangkat | Pilihan ganda | HP Android · iPhone · Laptop/PC · Tablet | Ya |
| 5 | Browser | Pilihan ganda | Chrome · Safari · Firefox · Brave · Edge · Lainnya | Ya |
| 6 | Tema saat menguji | Pilihan ganda | Terang · Gelap | Ya |

## Bagian 2: Temuanmu

| No | Pertanyaan | Jenis | Pilihan atau petunjuk | Wajib |
|---|---|---|---|---|
| 7 | Jenis temuan | Pilihan ganda | Error atau tidak bisa lanjut · Tampilan berantakan · Teks salah atau membingungkan · Lambat · Email tidak masuk · Saran fitur | Ya |
| 8 | Halaman atau fitur | Drop-down | Beranda · Cari Talenta · Profil talenta · Buat/Ubah Profil · Tawaran rekrut · Lamaran · Lowongan · Pasang Lowongan · Profil Perusahaan · Pelamar · Daftar/Masuk · Lupa kata sandi · Verifikasi email · Tentang Kami/Kontak · Pengaturan Akun · Panel Admin · Lainnya | Ya |
| 9 | Link halaman | Jawaban singkat | Salin dari address bar browser | Tidak |
| 10 | Judul singkat masalah | Jawaban singkat | Contoh: "Tombol Simpan tidak merespons di langkah 3" | Ya |
| 11 | Langkah yang kamu lakukan | Paragraf | Tulis berurutan. 1. Buka … 2. Klik … 3. Isi … | Ya |
| 12 | Yang kamu harapkan terjadi | Paragraf | | Ya |
| 13 | Yang benar-benar terjadi | Paragraf | Tulis juga pesan error yang muncul, kalau ada | Ya |
| 14 | Screenshot atau rekaman layar | Upload file | Maks 5 file, 10 MB. Gambar atau video | Tidak |
| 15 | Seberapa sering terjadi | Pilihan ganda | Selalu · Kadang-kadang · Sekali saja | Ya |
| 16 | Tingkat keparahan | Pilihan ganda | Lihat tabel di bawah | Ya |

Pilihan untuk pertanyaan 16 (tulis lengkap dengan penjelasannya supaya penguji tidak bingung):

| Pilihan | Artinya |
|---|---|
| Kritis | Data orang lain terlihat, atau sama sekali tidak bisa lanjut |
| Tinggi | Fitur utama gagal, tapi ada cara lain untuk menyelesaikannya |
| Sedang | Mengganggu, tapi fitur tetap bisa dipakai |
| Rendah | Kosmetik: salah ketik, warna, jarak, ikon |

## Bagian 3: Penilaian keseluruhan (isi sekali saja, di akhir pengujian)

Buat sebagai **form kedua** supaya penguji tidak mengisinya tiap temuan. Judul: **Penilaian Kabelota**.

| No | Pertanyaan | Jenis | Pilihan atau petunjuk | Wajib |
|---|---|---|---|---|
| 1 | Nama | Jawaban singkat | | Ya |
| 2 | Seberapa mudah kamu memahami Kabelota di kunjungan pertama? | Skala linier 1–5 | 1 = sangat membingungkan, 5 = sangat jelas | Ya |
| 3 | Seberapa nyaman tampilannya di HP? | Skala linier 1–5 | 1 = sangat tidak nyaman, 5 = sangat nyaman | Ya |
| 4 | Seberapa cepat halaman terasa dibuka? | Skala linier 1–5 | 1 = sangat lambat, 5 = sangat cepat | Ya |
| 5 | Kalau kamu alumni, apakah kamu mau memakai Kabelota untuk mencari kerja? | Pilihan ganda | Ya, pasti · Mungkin · Tidak · Saya bukan alumni | Ya |
| 6 | Fitur apa yang paling kamu suka? | Paragraf | | Tidak |
| 7 | Apa yang paling perlu diperbaiki sebelum diluncurkan? | Paragraf | | Ya |
| 8 | Fitur apa yang kamu harapkan ada tapi belum ada? | Paragraf | | Tidak |

---

## Pesan setelah form dikirim (Setelan → Presentasi → Pesan konfirmasi)

> Terima kasih, laporanmu sudah masuk. Kami cek dan kabari kalau butuh info tambahan. Kalau ada temuan lain, isi form ini lagi.

---

## Panduan singkat untuk penguji (kirim di grup bersama link form)

> **Cara menguji Kabelota**
> 1. Buka https://project-kabelota-production-ba0a.up.railway.app (di HP dulu, lalu laptop). Kunjungan pertama bisa butuh 10-30 detik karena server tidur kalau lama tidak dipakai.
> 2. Mau cepat? Klik **Masuk**, lalu pilih tombol **HRD Perusahaan** atau **Talenta** untuk masuk tanpa akun.
> 3. Mau uji pendaftaran? Daftar pakai email asli, lalu cek inbox (dan folder Spam) untuk link verifikasi.
> 4. Ikuti daftar tugas di SKENARIO-QA, atau jelajahi bebas.
> 5. Setiap menemukan masalah, isi Google Form: [link form]. Satu form untuk satu temuan.
> 6. Di akhir, isi **Penilaian Kabelota**: [link form kedua].
>
> Jangan unggah dokumen asli yang sensitif. Data di server ini bisa direset kapan saja.

---

## Kolom rekap di Google Sheets

Tambahkan di kanan kolom jawaban. Diisi oleh Nakita atau QA lead, bukan oleh penguji.

| Kolom | Isi |
|---|---|
| ID | QA-001, QA-002, … |
| Status | Baru · Dikerjakan · Selesai · Bukan bug · Ditunda (Validasi data → Drop-down) |
| PIC | Siapa yang memperbaiki |
| Rilis QA | Nomor rilis tempat temuan muncul, mis. QA-1 |
| Catatan perbaikan | Ringkasan perbaikan + kode commit |

Tips: Format → Pemformatan bersyarat, warnai baris "Kritis" merah dan "Tinggi" oranye.
