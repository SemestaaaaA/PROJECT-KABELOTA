<x-layouts.app title="Lamaran Saya · Kabelota">
<div class="wrap" style="max-width:900px">
    <div class="page-h">
        <h1>Lamaran saya</h1>
        <p>Status diperbarui oleh perusahaan. Kontak Anda terbuka untuk perusahaan saat lamaran berstatus Diterima.</p>
    </div>
    <x-page-tabs :items="[['Tawaran', 'talent.offers', auth()->user()->pendingOfferCount()], ['Lamaran Saya', 'talent.applications'], ['Profil', 'profile.edit']]" />

    @forelse ($applications as $app)
        @php($job = $app->jobPosting)
        <article class="inbox-card">
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
            @php($reached = ['baru' => 0, 'ditinjau' => 1, 'diterima' => 2, 'ditolak' => 2][$app->status])
            <ol class="progress-steps" aria-label="Tahap lamaran">
                @foreach (['Terkirim', 'Ditinjau', $app->status === 'ditolak' ? 'Tidak lanjut' : 'Diterima'] as $i => $label)
                    <li @class(['done' => $i <= $reached && ! ($i === 2 && $app->status === 'ditolak'), 'bad' => $i === 2 && $app->status === 'ditolak'])>{{ $label }}</li>
                @endforeach
            </ol>
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
