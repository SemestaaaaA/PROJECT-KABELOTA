<x-layouts.app title="Tentang Kami · Kabelota">
<div class="wrap">
    <div class="co-hero">
        <div>
            <span class="lbl">Tentang kami</span>
            <h1 style="margin-top:10px">Kebaikan untuk bersama.</h1>
            <p class="lead" style="margin-top:16px">"Kabelota" berasal dari bahasa Kaili. Kami memakainya sebagai janji: platform ini ada supaya lulusan Teknik Sipil daerah dan perusahaan yang membangun daerah saling menemukan.</p>
        </div>
        <div class="ph chamfer" role="img" aria-label="{{ config('kabelota.photos.about_page.alt') }}" style="background-image:url({{ config('kabelota.photos.about_page.src') }})"></div>
    </div>
</div>

<section class="wrap" aria-labelledby="why-h">
    <div class="split">
        <div>
            <h2 class="h2" id="why-h">Kenapa HMTS memulai ini</h2>
        </div>
        <div class="prose">
            <p>Setiap tahun, mahasiswa Teknik Sipil Universitas Tadulako lulus dengan ilmu yang dibutuhkan proyek di sekitar kampus: jalan, jembatan, irigasi, gedung, sampai penanganan tanah pascabencana. Tapi jalur antara lulusan dan perusahaan masih bergantung pada grup WhatsApp, kenalan dosen, dan kabar dari mulut ke mulut. Yang tidak punya kenalan sering tidak terdengar sama sekali.</p>
            <p>Himpunan Mahasiswa Teknik Sipil (HMTS) Universitas Tadulako melihat masalah ini dari dua sisi. Alumni sering bertanya ke himpunan apakah ada lowongan, sementara perusahaan dan konsultan menghubungi pengurus untuk mencari tenaga ahli yang SKK-nya sesuai dokumen tender. HMTS menjadi perantara tanpa sistem, dan banyak kesempatan terlewat karena informasinya tidak tercatat.</p>
            <p class="pullquote">Kabelota adalah cara HMTS mengubah perantara manual itu menjadi satu pintu yang terbuka untuk semua anggota, bukan hanya yang punya kenalan.</p>
            <p>Karena berangkat dari himpunan mahasiswa, aturan dasarnya sederhana: talenta tidak pernah membayar, kontak pribadi tidak dipajang, dan pendapatan dari lowongan dipakai untuk menjalankan platform. Pengembangannya didukung RINOYA UNTAD.</p>
            <p class="draft-note">Draf narasi untuk dikonfirmasi HMTS. Detail seperti tahun berdiri himpunan, jumlah anggota, atau kutipan pengurus sengaja belum ditulis supaya tidak ada fakta yang keliru.</p>
        </div>
    </div>
</section>

<section class="wrap" aria-labelledby="what-h">
    <div class="split">
        <div><h2 class="h2" id="what-h">Apa yang kami jaga</h2></div>
        <div class="co-points" style="margin-top:0;grid-template-columns:1fr">
            <div><i class="ph ph-hand-heart ph-icon" aria-hidden="true"></i><span><b>Gratis untuk talenta, selamanya</b>Alumni dan mahasiswa tidak pernah dipungut biaya untuk profil, melamar, atau menerima tawaran.</span></div>
            <div><i class="ph ph-lock-key ph-icon" aria-hidden="true"></i><span><b>Data pribadi dilindungi</b>Kontak terbuka hanya dengan persetujuan talenta, sesuai UU No. 27 Tahun 2022.</span></div>
            <div><i class="ph ph-map-pin ph-icon" aria-hidden="true"></i><span><b>Mengutamakan talenta lokal</b>Dimulai dari Teknik Sipil UNTAD, untuk proyek di Sulawesi Tengah dan sekitarnya.</span></div>
        </div>
    </div>
</section>

<section class="wrap" aria-labelledby="faq-h">
    <div class="faq">
        <div><h2 class="h2" id="faq-h">Pertanyaan yang sering muncul</h2></div>
        <x-faq />
    </div>
</section>
</x-layouts.app>
