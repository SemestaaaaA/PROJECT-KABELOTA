<x-layouts.app title="Untuk Perusahaan · Kabelota">
<div class="wrap">
    <div class="co-hero">
        <div>
            <x-cms key="companies.hero.title" tag="h1">Tenaga ahli lokal untuk tender Anda.</x-cms>
            <x-cms key="companies.hero.lead" tag="p" class="lead" style="margin-top:16px">Cari dan hubungi alumni Teknik Sipil UNTAD tanpa biaya. Bayar hanya kalau Anda memasang lowongan.</x-cms>
            <div class="row" style="display:flex;gap:10px;margin-top:22px;flex-wrap:wrap">
                <a class="btn btn-ink" href="{{ route('talents.index') }}">Cari Talenta</a>
                <x-post-job-button class="btn btn-accent" />
            </div>
        </div>
        <div class="ph chamfer" role="img" aria-label="{{ config('kabelota.photos.companies_page.alt') }}" style="background-image:url({{ config('kabelota.photos.companies_page.src') }})"></div>
    </div>

    <div class="co-points">
        <div><i class="ph ph-funnel ph-icon" aria-hidden="true"></i><span><x-cms key="companies.points.1.title" tag="b">Filter sesuai tabel tender</x-cms><x-cms key="companies.points.1.desc" tag="span">Jabatan kerja SKK, jenjang 7 sampai 9, pengalaman minimal, dan lokasi penempatan.</x-cms></span></div>
        <div><i class="ph ph-seal-check ph-icon" aria-hidden="true"></i><span><x-cms key="companies.points.2.title" tag="b">Masa berlaku SKK terlihat</x-cms><x-cms key="companies.points.2.desc" tag="span">Sertifikat yang kedaluwarsa langsung ditandai, jadi tidak ada kejutan saat evaluasi dokumen.</x-cms></span></div>
        <div><i class="ph ph-lock-key ph-icon" aria-hidden="true"></i><span><x-cms key="companies.points.3.title" tag="b">Kontak lewat persetujuan</x-cms><x-cms key="companies.points.3.desc" tag="span">Tawaran dikirim lewat sistem; kontak terbuka setelah talenta menerima.</x-cms></span></div>
        <div><i class="ph ph-clipboard-text ph-icon" aria-hidden="true"></i><span><x-cms key="companies.points.4.title" tag="b">Riwayat tawaran tercatat</x-cms><x-cms key="companies.points.4.desc" tag="span">Lihat siapa yang sudah dihubungi, menerima, atau belum membalas.</x-cms></span></div>
    </div>
</div>

<section class="wrap" id="biaya" aria-labelledby="price-h">
    <x-cms key="companies.price.title" tag="h2" class="h2" id="price-h">Biaya pasang lowongan</x-cms>
    <x-cms key="companies.price.lead" tag="p" class="lead" style="margin-top:14px">Mencari talenta dan mengajukan rekrut tidak dipungut biaya. Perusahaan membayar hanya saat memasang lowongan.</x-cms>
    <div class="price">
        <div class="free">
            <div><span class="lbl" style="color:#8E8E95">Untuk alumni dan mahasiswa</span><div class="big">Rp0</div></div>
            <x-cms key="companies.price.free" tag="p">Profil, pencarian lowongan, melamar, dan menerima tawaran. Selamanya.</x-cms>
        </div>
        <div class="list">
            @foreach (config('kabelota.packages') as $key => $pkg)
                <div @class(['prow', 'hl' => $key === 'tenaga_ahli'])>
                    <div><b>{{ $key === 'tenaga_ahli' ? 'Tenaga Ahli / Tender' : $pkg['label'] }}</b>
                        <small>{{ ['magang' => 'Untuk mahasiswa semester akhir', 'reguler' => 'Posisi umum di proyek', 'tenaga_ahli' => 'Tampil paling atas dan ditandai kuning'][$key] }}</small></div>
                    <span class="amt">Rp{{ number_format($pkg['price'], 0, ',', '.') }}</span>
                    <span class="dur">{{ $pkg['days'] }} hari</span>
                </div>
            @endforeach
            <x-post-job-button class="btn btn-ink" />
        </div>
    </div>
</section>

@php($payFlow = [
    ['ph-list-checks', 'Pilih paket', 'Magang, Reguler, atau Tenaga Ahli sesuai posisi yang dicari.'],
    ['ph-bank', 'Transfer', 'Bayar ke rekening Kabelota sesuai harga paket.'],
    ['ph-upload-simple', 'Unggah bukti', 'Foto atau PDF bukti transfer, maksimal 2 MB.'],
    ['ph-megaphone', 'Lowongan tayang', 'Admin mengecek bukti, biasanya di hari kerja yang sama.'],
])
<section class="wrap flow-h" aria-labelledby="pay-h">
    <x-cms key="companies.flow.title" tag="h2" class="h2" id="pay-h">Cara memasang lowongan</x-cms>
    <ol class="flow" style="--n: 4">
        @foreach ($payFlow as $i => [$icon, $title, $text])
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
