# Spec: Mode CMS (proyek 5 dari 5)

## Tujuan
Admin mengedit teks halaman marketing langsung di situs publik, dengan konfirmasi sebelum menyimpan, riwayat, dan pengembalian.

## Keputusan pengguna
- Edit langsung di halaman (bukan formulir admin).
- Cakupan: Beranda, Tentang Kami, Untuk Talenta, Untuk Perusahaan, Kontak. Tidak termasuk halaman hukum, FAQ, form, email.
- Format: teks + tebal, miring, tautan.
- Riwayat bertingkat + "Kembalikan ke teks asli".
- Pendekatan A: komponen `<x-cms>` + override di DB.

## Data
- `site_texts`: `key` (string 120, unik), `html` (text), `updated_by` (FK users nullOnDelete), timestamps.
- `site_text_revisions`: `id`, `key`, `html` (isi sebelum diganti, nullable bila sebelumnya teks bawaan), `user_id` (nullOnDelete), `created_at`; indeks `key`.
- Teks bawaan tetap di Blade (slot komponen) dan jadi cadangan.

## Komponen
- `<x-cms key="about.hero.lead" tag="p" class="lead">teks bawaan</x-cms>`: render `tag` berisi override tersaring bila ada, selain itu slot. Dalam mode edit menambah atribut `data-cms-key`, `data-cms-default` dan kelas `cms-editable`.
- `App\Support\SiteText`: `all()` memuat semua override sekali per permintaan (cache `forever`, dibersihkan saat simpan), `get($key)`, `sanitize($html)`, `save($key, $html, $user, $expectedUpdatedAt)`, `restore($key, $revisionId|null, $user)`.
- Daftar kunci sah dibangun dari pemakaian `<x-cms>` di Blade lewat konstanta `SiteText::KEYS`; kunci di luar daftar ditolak. Tes memastikan daftar cocok dengan view.
- Judul poin dan deskripsi poin dipisah jadi dua kunci supaya ikon/tata letak tidak ikut teredit.

## Penyaringan HTML (server)
Hanya `b strong i em br a`. `a`: `href` http(s), mailto, atau path relatif; `rel="noopener noreferrer"` dan `target` dibuang/diatur server. Semua atribut lain dibuang. Maks 2000 karakter (teks tanpa tag, bukan nol). Penyaringan memakai DOMDocument + allowlist, bukan regex.

## Mode edit (hanya admin)
- Tombol melayang "Mode edit" muncul hanya bila `auth()->user()?->isAdmin()`; status di session (`POST /cms/mode` toggle). JS editor (`resources/js/cms.js`) dimuat hanya bagi admin dalam mode edit.
- Klik elemen `.cms-editable` → `contenteditable`, toolbar mini (tebal, miring, tautan). "Simpan" membuka modal "Simpan perubahan?" dengan teks lama dan baru berdampingan. Batal mengembalikan teks semula.
- Menu "Riwayat teks ini": daftar revisi (siapa, kapan, potongan teks) dengan pilihan untuk memulihkan; tombol "Kembalikan ke teks asli". Semuanya lewat konfirmasi.

## Rute
- `POST /cms/mode` (admin) toggle mode.
- `PUT /cms/{key}` (admin, throttle 30/menit): body `html`, `expected` (updated_at yang dilihat editor atau kosong). Beda `expected` → 409 "Teks sudah diubah admin lain, muat ulang".
- `GET /cms/{key}/riwayat` (admin) daftar 20 revisi terakhir (JSON).
- `POST /cms/{key}/pulihkan` (admin): `revision` id atau kosong = teks asli.
- Non-admin: 403.

## Pencatatan
Simpan dan pulihkan menulis revisi + `AdminActivity::record('text_edited' | 'text_restored', $siteText, 'Teks "<key>" ...')`. Aksi ditambahkan ke `AdminActivity::ACTIONS` ("Teks situs diubah", "Teks situs dikembalikan").

## Pengujian
Non-admin 403 di semua rute; JS/tombol tak ada di HTML non-admin; script, `javascript:` dan `onclick` tersaring; kunci tak dikenal 422; DB kosong → teks bawaan; simpan membuat revisi + aktivitas; konflik 409; pulihkan revisi dan teks asli; daftar kunci cocok dengan view. Cek browser: edit, konfirmasi, kembalikan di beranda dan Tentang Kami, mode gelap.
