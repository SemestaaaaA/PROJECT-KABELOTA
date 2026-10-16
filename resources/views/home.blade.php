<x-layouts.app>
<x-slot:head>
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Kabelota',
        'url' => url('/'),
        'logo' => asset('images/brand/kabelota-mail.png'),
        'description' => 'Platform talenta Teknik Sipil Universitas Tadulako, diselenggarakan oleh HMTS Universitas Tadulako.',
        'sameAs' => [config('kabelota.contact.instagram')],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</x-slot:head>

<section class="hero wrap" aria-labelledby="hero-h">
    <div class="marks">
        <x-cms key="home.hero.title" tag="h1" id="hero-h">Tenaga ahli daerah, membangun daerahnya.</x-cms>
    </div>
    <x-cms key="home.hero.sub" tag="p" class="sub">Cari alumni dan mahasiswa Teknik Sipil UNTAD berdasarkan SKK, jenjang, dan pengalaman. Sesuai syarat tender Anda.</x-cms>

    <div class="stage" id="cari" x-data="{ tab: 'talenta' }">
        <div class="search">
            <div class="tabs" role="tablist" aria-label="Jenis pencarian">
                <button type="button" class="tab" role="tab" :aria-selected="tab === 'talenta'" @click="tab = 'talenta'">Cari Talenta</button>
                <button type="button" class="tab" role="tab" :aria-selected="tab === 'lowongan'" @click="tab = 'lowongan'">Cari Lowongan</button>
            </div>

            <form class="row" role="tabpanel" x-show="tab === 'talenta'" action="{{ route('talents.index') }}" method="get">
                <div class="f"><label for="s-jab">Jabatan kerja SKK</label>
                    <select class="inp" id="s-jab" name="jabatan">
                        <option value="">Semua jabatan</option>
                        @foreach (array_keys(config('kabelota.jabatan_kerja')) as $j)<option>{{ $j }}</option>@endforeach
                    </select></div>
                <div class="f"><label for="s-jen">Jenjang</label>
                    <select class="inp" id="s-jen" name="jenjang[]">
                        <option value="">Semua</option>
                        @foreach (config('kabelota.jenjang') as $lvl => $label)<option value="{{ $lvl }}">{{ $lvl }} {{ $label }}</option>@endforeach
                    </select></div>
                <div class="f"><label for="s-lok">Lokasi</label>
                    <select class="inp" id="s-lok" name="lokasi">
                        <option value="">Sulawesi Tengah</option>
                        @foreach (config('kabelota.locations') as $l)<option>{{ $l }}</option>@endforeach
                    </select></div>
                <button class="btn btn-accent" type="submit">Cari</button>
            </form>

            <form class="row" role="tabpanel" x-show="tab === 'lowongan'" x-cloak action="{{ route('jobs.index') }}" method="get">
                <div class="f" style="grid-column:span 2"><label for="s-pos">Posisi</label><input class="inp" id="s-pos" name="q" placeholder="mis. site engineer, drafter"></div>
                <div class="f"><label for="s-lok2">Lokasi</label>
                    <select class="inp" id="s-lok2" name="lokasi">
                        <option value="">Sulawesi Tengah</option>
                        @foreach (config('kabelota.locations') as $l)<option>{{ $l }}</option>@endforeach
                    </select></div>
                <button class="btn btn-accent" type="submit">Cari</button>
            </form>

            <p class="hint" x-text="tab === 'talenta'
                ? 'Lihat hasil tanpa daftar. Profil lengkap dan Ajukan Rekrut untuk perusahaan terverifikasi.'
                : 'Melamar memakai profil dan CV Anda di Kabelota. Gratis.'">Lihat hasil tanpa daftar. Profil lengkap dan Ajukan Rekrut untuk perusahaan terverifikasi.</p>
        </div>
        <div class="shot" role="img" aria-label="{{ config('kabelota.photos.hero.alt') }}" style="background-image:url({{ config('kabelota.photos.hero.src') }})"></div>
    </div>
</section>

<div class="wrap">
    <div class="stats" aria-label="Kabelota dalam angka">
        @foreach ($stats as $stat)
            <div class="stat">
                <span class="num">{{ number_format($stat['value'], 0, ',', '.') }}</span>
                <b>{{ $stat['label'] }}</b>
                <span>{{ $stat['note'] }}</span>
            </div>
        @endforeach
    </div>
    <p class="stats-note">Angka dihitung langsung dari database.</p>
</div>

@php($flows = [
    'co' => [
        ['ph-buildings', 'Daftar dan verifikasi', 'Unggah NIB atau SBU. Admin mengecek sebelum akun aktif.'],
        ['ph-funnel', 'Saring talenta', 'Pilih jabatan kerja SKK, jenjang, pengalaman, dan lokasi.'],
        ['ph-paper-plane-tilt', 'Ajukan rekrut', 'Tulis posisi dan pesan singkat. Talenta menerima email.'],
        ['ph-handshake', 'Kontak terbuka', 'Kalau talenta menerima, nomor dan emailnya muncul untuk Anda.'],
    ],
    'ta' => [
        ['ph-user-plus', 'Daftar gratis', 'Pilih Alumni atau Mahasiswa. Tidak ada biaya apa pun.'],
        ['ph-certificate', 'Lengkapi SKK dan proyek', 'Isi sertifikat, riwayat proyek, dan unggah CV sekali saja.'],
        ['ph-magnifying-glass', 'Ditemukan perusahaan', 'Profil Anda muncul saat HRD mencari sesuai syarat tender.'],
        ['ph-check-circle', 'Tertarik atau lamar', 'Pilih tawaran yang cocok, atau lamar lowongan yang sedang buka.'],
    ],
])
<section class="wrap flow-h" id="cara-kerja" aria-labelledby="how-h" x-data="{ who: 'co' }">
    <x-cms key="home.how.title" tag="h2" class="h2" id="how-h">Alur kerja</x-cms>
    <x-cms key="home.how.lead" tag="p" class="lead">Empat langkah dari mencari sampai terhubung. Kontak hanya terbuka kalau talenta setuju.</x-cms>
    <div class="tabs" role="tablist" aria-label="Alur kerja untuk">
        <button type="button" class="tab" role="tab" :aria-selected="who === 'co'" @click="who = 'co'">Perusahaan</button>
        <button type="button" class="tab" role="tab" :aria-selected="who === 'ta'" @click="who = 'ta'">Talenta</button>
    </div>
    @foreach ($flows as $key => $steps)
        <ol class="flow" style="--n: {{ count($steps) }}" x-show="who === '{{ $key }}'" @if ($key === 'ta') x-cloak @endif>
            @foreach ($steps as $i => [$icon, $title, $text])
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
    @endforeach
</section>

<section class="wrap" aria-labelledby="ben-h" x-data="{ who: 'co' }">
    <x-cms key="home.why.title" tag="h2" class="h2" id="ben-h">Kenapa Kabelota</x-cms>
    <div class="tabs" role="tablist" aria-label="Manfaat untuk" style="margin-top:18px">
        <button type="button" class="tab" role="tab" :aria-selected="who === 'co'" @click="who = 'co'">Perusahaan</button>
        <button type="button" class="tab" role="tab" :aria-selected="who === 'ta'" @click="who = 'ta'">Talenta</button>
    </div>

    <div class="bento" x-show="who === 'co'">
        <article class="tile big chamfer">
            <div class="ph" role="img" aria-label="{{ config('kabelota.photos.talent.alt') }}" style="background-image:url({{ config('kabelota.photos.talent.src') }})"></div>
            <div class="tx">
                <x-cms key="home.why.co.1.title" tag="h3">Saring sesuai tabel kebutuhan tender</x-cms>
                <x-cms key="home.why.co.1.desc" tag="p">Pilih jabatan kerja, jenjang, pengalaman minimal, dan lokasi. Yang muncul hanya orang yang memenuhi syarat dokumen penawaran Anda.</x-cms>
                <div class="chips" aria-hidden="true"><span class="chip on">Ahli Teknik Jalan</span><span class="chip on">Jenjang 7+</span><span class="chip">Min. 4 thn</span><span class="chip">Palu, Sigi</span></div>
            </div>
        </article>
        <article class="tile yellow marks">
            <x-cms key="home.why.co.2.title" tag="h3">CV dan SKK siap diperiksa</x-cms>
            <x-cms key="home.why.co.2.desc" tag="p">Dokumen tersimpan privat dan hanya bisa diunduh perusahaan yang sudah diverifikasi.</x-cms>
        </article>
        <article class="tile">
            <x-cms key="home.why.co.3.title" tag="h3">Riwayat tawaran tercatat</x-cms>
            <x-cms key="home.why.co.3.desc" tag="p">Siapa yang sudah dihubungi bulan ini, siapa yang menerima, dan siapa yang belum membalas, semuanya ada di satu dashboard.</x-cms>
        </article>
    </div>

    <div class="bento" x-show="who === 'ta'" x-cloak>
        <article class="tile big chamfer">
            <div class="ph" role="img" aria-label="{{ config('kabelota.photos.company.alt') }}" style="background-image:url({{ config('kabelota.photos.company.src') }})"></div>
            <div class="tx">
                <x-cms key="home.why.ta.1.title" tag="h3">Gratis, dan akan tetap gratis</x-cms>
                <x-cms key="home.why.ta.1.desc" tag="p">Isi profil sekali: SKK, riwayat proyek, CV. Perusahaan yang mencari Anda, dan merekalah yang membayar.</x-cms>
            </div>
        </article>
        <article class="tile yellow marks">
            <x-cms key="home.why.ta.2.title" tag="h3">Nomor Anda tetap privat</x-cms>
            <x-cms key="home.why.ta.2.desc" tag="p">Tawaran masuk lewat email dan dashboard. Anda memilih Tertarik atau Tolak; kontak hanya terbuka kalau Anda menyatakan tertarik.</x-cms>
        </article>
        <article class="tile">
            <x-cms key="home.why.ta.3.title" tag="h3">Lowongan dari proyek di sekitar Anda</x-cms>
            <x-cms key="home.why.ta.3.desc" tag="p">Perusahaan di Palu, Sigi, Donggala, dan kabupaten lain memasang lowongan magang sampai posisi tenaga ahli.</x-cms>
        </article>
    </div>
</section>

<section class="wrap" id="lowongan" aria-labelledby="job-h">
    <div class="jobs-h">
        <h2 class="h2" id="job-h">Lowongan terbaru</h2>
        <a class="textlink" href="{{ route('jobs.index') }}">Lihat semua lowongan</a>
    </div>
    <div class="jobs">
        @foreach ($jobs as $job)<x-job-card :job="$job" />@endforeach
    </div>
</section>

<section class="wrap" id="tentang" aria-labelledby="story-h">
    <div class="story">
        <div class="ph chamfer" role="img" aria-label="{{ config('kabelota.photos.cta.alt') }}" style="background-image:url({{ config('kabelota.photos.cta.src') }})"></div>
        <div>
            <span class="lbl" id="story-h">Tentang kami</span>
            <x-cms key="home.story.quote" tag="blockquote" style="margin-top:12px">"Kabelota" dalam bahasa Kaili berarti <em>kebaikan untuk bersama.</em></x-cms>
            <x-cms key="home.story.body" tag="p">Proyek jalan, irigasi, dan gedung di Sulawesi Tengah butuh tenaga ahli bersertifikat, dan banyak di antaranya lulus dari kampus yang sama di Palu. Kabelota memastikan perusahaan bisa menemukan mereka lebih dulu, sebelum mencari ke luar daerah.</x-cms>
            <a class="textlink" href="{{ route('about') }}" style="display:inline-block;margin-top:14px">Baca cerita lengkapnya</a>
        </div>
    </div>
</section>

<section class="wrap" aria-labelledby="faq-h">
    <div class="faq">
        <div><h2 class="h2" id="faq-h">Pertanyaan yang sering muncul</h2></div>
        <div>
            <x-faq />
        </div>
    </div>
</section>

<section class="wrap" id="daftar" aria-label="Mulai">
    <div class="final">
        <div class="co chamfer">
            <span class="lbl" style="color:#8E8E95">Untuk perusahaan</span>
            <x-cms key="home.final.co.title" tag="h3">Butuh tenaga ahli untuk tender berikutnya?</x-cms>
            <x-cms key="home.final.co.desc" tag="p">Pasang lowongan atau langsung cari talenta yang memenuhi syarat.</x-cms>
            <a class="btn btn-accent" href="{{ route('companies') }}">Pasang Lowongan</a>
        </div>
        <div class="ta chamfer">
            <span class="lbl" style="color:#3D3000">Untuk alumni dan mahasiswa</span>
            <x-cms key="home.final.ta.title" tag="h3">Biar perusahaan yang menemukan Anda.</x-cms>
            <x-cms key="home.final.ta.desc" tag="p">Isi profil dan SKK sekali, lalu terima tawaran dari proyek di Sulawesi Tengah.</x-cms>
            <button type="button" class="btn btn-ink" @click="$store.auth.show({ tab: 'daftar', role: 'talenta' })">Daftar Gratis</button>
        </div>
    </div>
</section>

</x-layouts.app>
