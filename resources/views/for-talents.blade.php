<x-layouts.app title="Untuk Talenta · Kabelota" description="Alumni dan mahasiswa Teknik Sipil UNTAD: buat profil gratis, ditemukan perusahaan konstruksi Sulawesi Tengah, dan pilih sendiri tawaran kerja Anda.">
<div class="wrap">
    <div class="co-hero">
        <div>
            <h1>Profil Anda dicari perusahaan konstruksi Sulawesi Tengah.</h1>
            <p class="lead" style="margin-top:16px">Buat profil sekali, tampilkan SKK dan pengalaman Anda, lalu biarkan perusahaan yang datang. Gratis untuk alumni dan mahasiswa.</p>
            <div class="row" style="display:flex;gap:10px;margin-top:22px;flex-wrap:wrap">
                @auth
                    <a class="btn btn-accent" href="{{ route('profile.edit') }}">Lengkapi Profil</a>
                @else
                    <button type="button" class="btn btn-accent" @click="$store.auth.show({ tab: 'daftar', role: 'talenta' })">Daftar Gratis</button>
                @endauth
                <a class="btn btn-ink" href="{{ route('jobs.index') }}">Lihat Lowongan</a>
            </div>
        </div>
        <div class="ph chamfer" role="img" aria-label="{{ config('kabelota.photos.talent.alt') }}" style="background-image:url({{ config('kabelota.photos.talent.src') }})"></div>
    </div>

    <div class="co-points">
        <div><i class="ph ph-gift ph-icon" aria-hidden="true"></i><span><b>Gratis selamanya</b>Profil, melamar lowongan, dan menerima tawaran tidak dipungut biaya.</span></div>
        <div><i class="ph ph-lock-key ph-icon" aria-hidden="true"></i><span><b>Kontak di tangan Anda</b>Nomor HP dan email hanya terbuka untuk perusahaan yang tawarannya Anda tanggapi dengan Tertarik.</span></div>
        <div><i class="ph ph-seal-check ph-icon" aria-hidden="true"></i><span><b>Pengingat SKK</b>Kami mengirim email sebelum sertifikat SKK Anda habis masa berlakunya.</span></div>
        <div><i class="ph ph-handshake ph-icon" aria-hidden="true"></i><span><b>Anda yang memilih</b>Tawaran kerja final Anda jawab sendiri. Setelah Anda mengambil satu, proses lain ditutup dengan rapi.</span></div>
    </div>
</div>

@php($talentFlow = [
    ['ph-user-circle-plus', 'Buat profil', 'Isi jenjang SKK, pengalaman proyek, dan unggah CV dalam empat langkah.'],
    ['ph-magnifying-glass', 'Ditemukan atau melamar', 'Perusahaan mencari Anda, atau Anda melamar lowongan yang cocok.'],
    ['ph-hand-heart', 'Tertarik', 'Tanggapi tawaran dengan Tertarik. Kontak Anda terbuka untuk perusahaan itu saja.'],
    ['ph-handshake', 'Ambil tawaran kerja', 'Pilih satu tawaran kerja final. Proses dengan perusahaan lain ditutup otomatis.'],
])
<section class="wrap flow-h" aria-labelledby="talent-flow-h">
    <h2 class="h2" id="talent-flow-h">Cara kerjanya</h2>
    <ol class="flow" style="--n: 4">
        @foreach ($talentFlow as $i => [$icon, $title, $text])
            <li>
                <div class="ic"><i class="ph {{ $icon }}" aria-hidden="true"></i></div>
                <span class="step">Langkah {{ $i + 1 }}</span>
                <b>{{ $title }}</b>
                <p>{{ $text }}</p>
                @unless ($loop->last)
                    <svg class="wire" viewBox="0 0 200 48" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40 C 60 40, 70 8, 100 8 S 150 40, 200 8" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>
                @endunless
            </li>
        @endforeach
    </ol>
</section>
</x-layouts.app>
