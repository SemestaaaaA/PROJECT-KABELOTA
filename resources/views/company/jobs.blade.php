<x-layouts.app title="Lowongan Saya · Kabelota">
<div class="wrap" style="max-width:1000px">
    <div class="page-h" style="display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap">
        <div><h1>Lowongan saya</h1><p>Status pembayaran, masa tayang, dan pelamar tiap lowongan.</p></div>
        <x-post-job-button class="btn btn-accent" />
    </div>
    <x-page-tabs :items="[['Lowongan Saya', 'company.jobs'], ['Tawaran Terkirim', 'company.offers'], ['Profil Perusahaan', 'company.profile']]" />

    @forelse ($jobs as $job)
        @php($live = $job->status === 'aktif' && $job->closes_at->gte(today()))
        <article class="inbox-card">
            <div class="inbox-head">
                <div class="co-logo" aria-hidden="true"><i class="ph ph-briefcase"></i></div>
                <div>
                    <h2>{{ $job->title }}</h2>
                    <p class="demo-note">{{ $job->packageLabel() }} · {{ $job->location }} · {{ $live ? 'tayang sampai '.$job->closes_at->translatedFormat('j M Y') : ($job->status === 'aktif' ? 'masa tayang habis' : '') }}</p>
                </div>
                <span @class(['badge', 'b-ok' => $live, 'b-contract' => $job->status === 'menunggu_verifikasi', 'b-bad' => $job->status === 'ditolak', 'b-off' => ($job->status === 'aktif' && ! $live) || $job->status === 'ditutup'])>
                    {{ ['menunggu_verifikasi' => 'Menunggu verifikasi', 'aktif' => $live ? 'Tayang' : 'Selesai', 'ditolak' => 'Pembayaran ditolak', 'ditutup' => 'Ditutup'][$job->status] ?? $job->status }}
                </span>
            </div>
            <div class="inbox-actions">
                @if ($job->status === 'menunggu_verifikasi')
                    <a class="btn btn-line btn-sm" href="{{ route('jobs.posting.status', $job) }}">Lihat status</a>
                @else
                    <a class="btn btn-ink btn-sm" href="{{ route('company.applicants', $job) }}"><i class="ph ph-users" aria-hidden="true"></i> {{ $job->applications_count }} pelamar</a>
                    @if ($job->new_applications_count)<span class="badge b-new">{{ $job->new_applications_count }} baru</span>@endif
                    @if ($live)
                        <form method="post" action="{{ route('company.jobs.close', $job) }}" style="margin-left:auto" onsubmit="return confirm('Tutup lowongan ini sekarang? Lowongan tidak tampil lagi dan talenta tidak bisa melamar. Sisa masa tayang tidak dikembalikan.')">
                            @csrf
                            <button class="btn btn-line btn-sm" type="submit"><i class="ph ph-lock-simple" aria-hidden="true"></i> Tutup lowongan</button>
                        </form>
                    @endif
                @endif
            </div>
        </article>
    @empty
        <div class="empty" style="margin-top:20px">
            <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada lowongan</b>
            Pasang lowongan pertama Anda. Mulai Rp50.000 untuk posisi magang.
        </div>
    @endforelse
</div>
</x-layouts.app>
