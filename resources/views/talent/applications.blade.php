<x-layouts.app title="Lamaran Saya · Kabelota">
<div class="wrap" style="max-width:900px">
    <div class="page-h">
        <h1>Lamaran saya</h1>
        <p>Status diperbarui oleh perusahaan. Kalau perusahaan mengirim tawaran kerja, Anda yang memutuskan: ambil atau tolak.</p>
    </div>
    <x-page-tabs :items="[['Tawaran', 'talent.offers', auth()->user()->pendingOfferCount()], ['Lamaran Saya', 'talent.applications', auth()->user()->pendingApplicationOfferCount()], ['Profil', 'profile.edit']]" />

    @forelse ($applications as $app)
        @php
            $job = $app->jobPosting;
        @endphp
        <article @class(['inbox-card', 'is-offer' => $app->isJobOffer(), 'is-past' => in_array($app->status, ['ditutup', 'kedaluwarsa'], true)])>
            <div class="inbox-head">
                <div class="co-logo" aria-hidden="true">
                    @if ($job->company?->logo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($job->company->logo_path) }}" alt="">@else<i class="ph ph-briefcase"></i>@endif
                </div>
                <div>
                    <h2>{{ $job->title }}</h2>
                    <p class="demo-note">{{ $job->company->name }} · {{ $job->location }} · dilamar {{ $app->created_at->diffForHumans() }}</p>
                </div>
                <span class="badge {{ $app->badgeClass() }}">{{ $app->statusLabel() }}</span>
            </div>
            @php
                $reached = ['baru' => 0, 'ditinjau' => 1, 'tawaran_kerja' => 2, 'diambil' => 3][$app->status] ?? null;
                $ended = ['ditolak' => 'Tidak lanjut', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa'][$app->status] ?? null;
            @endphp
            <ol class="progress-steps" aria-label="Tahap lamaran">
                @foreach (['Terkirim', 'Ditinjau', 'Tawaran kerja', $ended ?? 'Diambil'] as $i => $label)
                    <li @class(['done' => $reached !== null && $i <= $reached, 'bad' => $ended && $i === 3])>{{ $label }}</li>
                @endforeach
            </ol>
            @if ($app->isJobOffer())
                <x-job-offer-panel :record="$app" :others="$openProcesses" />
            @elseif ($app->status === 'ditutup')
                <p class="demo-note">Ditutup otomatis karena Anda mengambil tawaran kerja lain.</p>
            @elseif ($app->status === 'kedaluwarsa')
                <p class="demo-note">Tawaran kerja kedaluwarsa karena tidak dijawab dalam 7 hari.</p>
            @endif
            @if ($app->message)<p class="inbox-msg">Pesan Anda: {{ $app->message }}</p>@endif
        </article>
    @empty
        <div class="empty" style="margin-top:20px">
            <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada lamaran</b>
            Lamar lowongan yang sesuai dengan konsentrasi dan SKK Anda. <a class="textlink" href="{{ route('jobs.index') }}">Lihat lowongan</a>
        </div>
    @endforelse
</div>
</x-layouts.app>
