<x-layouts.app title="Dashboard Perusahaan · Kabelota">
@php
    $todo = $d->todo();
    $offers = $d->jobOffers();
    $jobs = $d->jobs();
    $profile = $d->profile();
@endphp
<div class="wrap" style="max-width:1100px">
    <div class="page-h" style="display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap">
        <div><h1>{{ $company->name }}</h1><p>Ringkasan rekrutmen Anda hari ini.</p></div>
        <x-post-job-button class="btn btn-accent" />
    </div>
    <x-company-tabs />

    <div class="bento">
        <section class="bento-card wide" aria-labelledby="b-todo">
            <div class="bento-head"><h2 id="b-todo" class="lbl">Perlu tindakan</h2>@if ($todo['total'])<span class="count">{{ $todo['total'] }}</span>@endif</div>
            @if ($todo['items'])
                <ul class="todo-list">
                    @foreach ($todo['items'] as $item)
                        <li><a href="{{ $item['url'] }}"><i class="ph {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['text'] }}</span><i class="ph ph-caret-right" aria-hidden="true"></i></a></li>
                    @endforeach
                </ul>
                @if ($todo['total'] > count($todo['items']))<p class="demo-note">+{{ $todo['total'] - count($todo['items']) }} lainnya di halaman pelamar dan tawaran.</p>@endif
            @else
                <div class="bento-empty"><i class="ph ph-check-circle" aria-hidden="true"></i><b>Semua beres</b><span>Tidak ada pelamar atau tawaran yang menunggu Anda.</span></div>
            @endif
        </section>

        <section class="bento-card" aria-labelledby="b-offers">
            <h2 id="b-offers" class="lbl">Tawaran kerja</h2>
            <p class="bento-num">{{ $offers['pending'] }}</p>
            <p class="demo-note">{{ $offers['pending'] ? 'menunggu jawaban talenta'.($offers['nearest'] ? ', batas terdekat '.$offers['nearest']->translatedFormat('j M') : '') : 'tidak ada yang menunggu jawaban' }}</p>
            <p class="bento-foot"><i class="ph ph-handshake" aria-hidden="true"></i> {{ $offers['taken'] }} diambil talenta</p>
        </section>

        <section class="bento-card" aria-labelledby="b-jobs">
            <h2 id="b-jobs" class="lbl">Lowongan aktif</h2>
            <p class="bento-num">{{ $jobs['live'] }}</p>
            <p class="demo-note">{{ $jobs['applicants'] }} pelamar di lowongan yang tayang</p>
            @if ($jobs['closingSoon'])<p class="bento-foot"><i class="ph ph-calendar" aria-hidden="true"></i> {{ \Illuminate\Support\Str::limit($jobs['closingSoon']->title, 32) }} tutup {{ $jobs['closingSoon']->closes_at->translatedFormat('j M') }}</p>@endif
            <a class="textlink" href="{{ route('company.jobs') }}">Lihat lowongan</a>
        </section>

        <section class="bento-card" aria-labelledby="b-contacts">
            <h2 id="b-contacts" class="lbl">Kontak terbuka</h2>
            <p class="bento-num">{{ $d->openContacts() }}</p>
            <p class="demo-note">talenta yang nomor HP dan emailnya bisa Anda lihat</p>
            <a class="textlink" href="{{ route('company.offers') }}">Lihat tawaran terkirim</a>
        </section>

        <section @class(['bento-card', 'alert' => ! $profile['verified']]) aria-labelledby="b-profile">
            <h2 id="b-profile" class="lbl">Profil perusahaan</h2>
            <p><span @class(['badge', 'b-ok' => $profile['verified'], 'b-bad' => $company->status === 'ditolak', 'b-offer' => $company->status === 'menunggu'])>{{ $profile['label'] }}</span></p>
            <div class="meter-bar" role="img" aria-label="Kelengkapan profil {{ $profile['percent'] }} persen"><span style="width:{{ $profile['percent'] }}%"></span></div>
            <p class="demo-note">{{ $profile['percent'] }}% lengkap{{ $profile['missing'] ? '. Belum ada: '.implode(', ', $profile['missing']) : '' }}</p>
            <a class="textlink" href="{{ route('company.profile') }}">{{ $profile['percent'] < 100 || ! $profile['verified'] ? 'Lengkapi profil' : 'Lihat profil' }}</a>
        </section>
    </div>
</div>
</x-layouts.app>
