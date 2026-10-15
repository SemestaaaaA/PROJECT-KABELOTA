# Spec: Perbaikan cepat portal (proyek 1 dari 5)

## Context
Masukan pengguna setelah alur keputusan talenta selesai:
- Talenta yang login melihat menu "Untuk Perusahaan", padahal harusnya punya halaman sendiri.
- `/kontak` tetap meminta nama dan email walaupun pengirim sudah login, dan tidak dijelaskan ke mana pesan dikirim.
- Di `/profil`, kolom email turun dibanding kolom HP, dan latar isian berwarna biru (autofill browser) di mode gelap.

## Keputusan
- Menu nav sesuai peran: talenta yang login melihat **Untuk Talenta**; perusahaan dan pengunjung melihat **Untuk Perusahaan**. Footer menampilkan keduanya.
- Komponen tombol "?" yang bisa dipakai ulang (`<x-help-tip>`), juga untuk admin nanti.

## Desain
1. **`/untuk-talenta`** (`Route::view`, nama `for-talents`, view `for-talents.blade.php`), gaya sama dengan `companies.blade.php` (`.co-hero`, `.co-points`, `.flow`):
   - Hero "Profil Anda dicari perusahaan konstruksi Sulawesi Tengah." + tombol: tamu → *Daftar Gratis* (membuka modal daftar, peran talenta); talenta → *Lengkapi Profil*; keduanya + *Lihat Lowongan*.
   - 4 poin: gratis selamanya; kontak terbuka hanya kalau Anda tertarik; pengingat SKK sebelum habis; Anda yang memilih tawaran kerja.
   - Alur 4 langkah: Buat profil → Ditemukan atau melamar → Tertarik (kontak terbuka) → Ambil tawaran kerja (proses lain ditutup otomatis).
2. **Nav**: desktop dan menu HP memakai `$me?->isTalent()` untuk memilih tautan; footer berisi keduanya.
3. **`/kontak` saat login**: kolom Nama/Email diganti "Dikirim sebagai **Nama** · email". Server memakai nama + email akun (perusahaan: nama perusahaan) dan menyimpan `contact_messages.user_id` (kolom baru, nullable, `nullOnDelete`). Tamu tetap wajib mengisi nama + email. Judul "Kirim pesan" diberi `<x-help-tip>`: "Pesan masuk ke panel admin Kabelota (pengurus HMTS) dan notifikasi dikirim ke email admin. Balasan dikirim ke email Anda, biasanya di hari kerja." Admin ContactMessages menandai pesan dari akun terdaftar.
4. **`<x-help-tip label="…">isi</x-help-tip>`**: tombol bulat "?", tooltip muncul saat hover, klik (HP) atau fokus keyboard; tertutup saat Escape, blur, atau klik di luar; `role="tooltip"` + `aria-describedby`.
5. **Form**: `.fld { align-content: start }` (kolom berdampingan tidak bergeser); gaya autofill mengikuti tema (`.box:-webkit-autofill` memakai `--surface`/`--ink`). Email profil terisi email akun kalau kosong + catatan "Email kontak untuk perusahaan, boleh beda dengan email login."

## Pengujian (`tests/Feature/PortalTest.php`)
- Nav: talenta melihat `/untuk-talenta` dan tidak melihat tautan nav `/untuk-perusahaan`; perusahaan dan tamu sebaliknya; footer selalu berisi keduanya.
- `/untuk-talenta` terbuka untuk tamu (tombol Daftar Gratis) dan talenta (tombol Lengkapi Profil).
- Kontak: tamu tetap wajib nama/email; pengguna login mengirim tanpa nama/email, isian palsu diabaikan, `user_id` tersimpan; tombol "?" tampil.
- Profil: talenta tanpa profil melihat email akunnya di kolom email.
