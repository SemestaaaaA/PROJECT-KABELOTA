<?php

return [
    'tagline' => 'Kebaikan untuk bersama',

    // Shows the "coba sebagai HRD/Talenta" buttons. Turn off for the real launch.
    'demo_mode' => env('KABELOTA_DEMO', true),

    // Server QA (APP_ENV=staging): pita "Versi QA" + tautan Google Form untuk melapor masalah.
    'qa_form_url' => env('KABELOTA_QA_FORM_URL'),

    // Foto situs. Ganti foto stok dengan foto asli: taruh file di public/images, lalu ubah 'src' dan 'alt' di sini.
    'photos' => [
        'hero' => ['src' => '/images/hero-jakarta-web.jpg', 'alt' => 'Pekerja berhelm kuning di perancah proyek gedung'],
        'talent' => ['src' => '/images/insinyur-banyumas-web.jpg', 'alt' => 'Barisan pekerja proyek berhelm kuning'],
        'company' => ['src' => '/images/tim-proyek-web.jpg', 'alt' => 'Pekerja proyek berompi oranye'],
        'cta' => ['src' => '/images/pekerja-tangga-web.jpg', 'alt' => 'Pekerja berompi oranye bekerja di samping tangga'],
        'companies_page' => ['src' => '/images/insinyur-banyumas-web.jpg', 'alt' => 'Barisan pekerja proyek berhelm kuning'],
        'about_page' => ['src' => '/images/tim-proyek-web.jpg', 'alt' => 'Barisan pekerja proyek berhelm kuning'],
    ],

    // Sandi akun admin dan HRD contoh untuk seeder. Wajib diisi di server (kosong hanya boleh di lokal).
    // Receives alerts: failed jobs and failed backups.
    'ops_email' => env('KABELOTA_OPS_EMAIL', 'admin@kabelota.id'),

    'admin_email' => env('KABELOTA_ADMIN_EMAIL', 'admin@kabelota.id'),
    'admin_password' => env('KABELOTA_ADMIN_PASSWORD'),
    'demo_password' => env('KABELOTA_DEMO_PASSWORD'),

    'concentrations' => ['Struktur', 'Transportasi', 'Keairan', 'Geoteknik', 'Manajemen Konstruksi'],

    // Jabatan kerja SKK yang paling relevan untuk tender di Sulawesi Tengah (dikonfirmasi ulang ke klien).
    'jabatan_kerja' => [
        'Ahli Teknik Jalan' => 'Transportasi',
        'Ahli Teknik Jembatan' => 'Struktur',
        'Ahli Teknik Bangunan Gedung' => 'Struktur',
        'Ahli Sumber Daya Air' => 'Keairan',
        'Ahli Teknik Bendungan Besar' => 'Keairan',
        'Ahli Geoteknik' => 'Geoteknik',
        'Ahli K3 Konstruksi' => 'Manajemen Konstruksi',
        'Ahli Manajemen Konstruksi' => 'Manajemen Konstruksi',
    ],

    // KKNI: 4-6 Teknisi/Analis, 7-9 Ahli.
    'jenjang' => [4 => 'Teknisi/Analis', 5 => 'Teknisi/Analis', 6 => 'Teknisi/Analis', 7 => 'Ahli Muda', 8 => 'Ahli Madya', 9 => 'Ahli Utama'],

    'experience_options' => [1, 2, 3, 4, 5, 10, 15],

    'outside_region' => 'Luar Sulawesi Tengah',

    'locations' => [
        'Palu', 'Sigi', 'Donggala', 'Parigi Moutong', 'Poso', 'Morowali',
        'Morowali Utara', 'Banggai', 'Tolitoli', 'Buol', 'Tojo Una-Una', 'Luar Sulawesi Tengah',
    ],

    'packages' => [
        'magang' => ['label' => 'Magang', 'price' => 50000, 'days' => 14],
        'reguler' => ['label' => 'Reguler', 'price' => 100000, 'days' => 30],
        'tenaga_ahli' => ['label' => 'Tenaga Ahli', 'price' => 200000, 'days' => 30],
    ],

    'per_page' => 20,

    // HMTS membership levels. Confirm the exact terms with HMTS AD/ART.
    'hmts_statuses' => [
        'muda' => 'Anggota Muda',
        'biasa' => 'Anggota Biasa',
        'pengurus' => 'Pengurus',
        'purna' => 'Purna Pengurus',
        'alumni' => 'Alumni HMTS',
        'pasif' => 'Pasif',
    ],

    'upload' => ['photo_kb' => 5120, 'document_kb' => 3072],

    'skills' => ['AutoCAD', 'Civil 3D', 'Revit', 'SAP2000', 'ETABS', 'SketchUp', 'MS Project', 'Primavera P6', 'HEC-RAS', 'Global Mapper', 'Excel (RAB)', 'Total Station'],

    // Placeholder account shown in the posting flow until the client confirms the real one.
    'bank' => ['name' => 'Bank Mandiri', 'number' => '000-00-0000000-0', 'holder' => 'Kabelota (placeholder)'],

    'contact' => [
        'instagram' => 'https://www.instagram.com/hmts.tadulako/',
        'instagram_handle' => '@hmts.tadulako',
        'email' => 'admin@kabelota.id', // placeholder
        'whatsapp' => '+62 812-0000-0000', // placeholder
    ],

    'faq' => [
        ['Apakah alumni dan mahasiswa perlu membayar?', 'Tidak. Talenta tidak pernah dipungut biaya, termasuk untuk melamar lowongan. Biaya hanya dikenakan ke perusahaan saat memasang lowongan.'],
        ['Bagaimana nomor HP dan email saya dilindungi?', 'Kontak tidak tampil di profil. Perusahaan mengirim tawaran lewat sistem, Anda memilih Terima atau Tolak, dan kontak hanya terbuka untuk perusahaan yang Anda terima.'],
        ['Apa itu SKK dan jenjang?', 'SKK (Sertifikat Kompetensi Kerja) Konstruksi menunjukkan jabatan kerja dan tingkat keahlian. Jenjang 4 sampai 6 untuk teknisi atau analis, jenjang 7 (Ahli Muda), 8 (Ahli Madya), dan 9 (Ahli Utama) untuk tenaga ahli. Dokumen tender biasanya menyebut jabatan dan jenjang minimal.'],
        ['Bagaimana perusahaan diverifikasi?', 'Saat mendaftar, perusahaan mengunggah NIB atau SBU. Admin Kabelota mengeceknya sebelum akun bisa melihat profil lengkap dan mengunduh CV.'],
        ['Bagaimana cara membayar lowongan?', 'Pilih paket, transfer ke rekening Kabelota, lalu unggah bukti transfer. Lowongan tayang setelah admin mengecek bukti, biasanya di hari kerja yang sama.'],
        ['Saya belum punya SKK. Apakah tetap bisa mendaftar?', 'Bisa. Isi riwayat proyek dan pendidikan Anda. Banyak lowongan magang dan posisi pelaksana tidak mensyaratkan SKK, dan Anda bisa menambahkannya kapan saja.'],
        ['Saya masih mahasiswa. Apa bedanya dengan alumni?', 'Saat membuat profil, Anda memilih status Mahasiswa. Profil Anda diberi tanda Intern for Hire sehingga perusahaan yang mencari tenaga magang langsung menemukan Anda.'],
        ['Apakah lulusan kampus selain UNTAD boleh mendaftar?', 'Untuk tahap pilot, Kabelota fokus pada alumni dan mahasiswa Teknik Sipil Universitas Tadulako. Kampus lain akan dibuka setelah pilot dievaluasi.'],
        ['Berapa lama lowongan tayang?', 'Paket Magang tayang 14 hari, Reguler dan Tenaga Ahli 30 hari. Lowongan tertutup sendiri ketika masa tayangnya habis.'],
        ['Bagaimana cara menghapus akun dan data saya?', 'Buka pengaturan profil lalu pilih Hapus Akun, atau kirim permintaan lewat halaman Kontak. Data Anda dihapus sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.'],
    ],
];
