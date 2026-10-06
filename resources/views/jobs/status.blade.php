@php
    $live = $job->status === 'aktif';
    $steps = [
        ['Lowongan dibuat', $job->created_at->translatedFormat('j M Y, H:i'), true],
        ['Bukti transfer diunggah', 'Rp'.number_format($job->packagePrice(), 0, ',', '.').' · paket '.$job->packageLabel(), true],
        ['Diverifikasi admin', $live ? $job->approved_at->translatedFormat('j M Y, H:i') : 'Biasanya di hari kerja yang sama', $live],
        ['Tayang di Kabelota', $live ? 'Sampai '.$job->closes_at->translatedFormat('j M Y') : 'Menunggu verifikasi', $live],
    ];
@endphp
<x-layouts.app title="Status Lowongan · Kabelota">
<div class="wrap" style="max-width:820px">
    <div class="page-h">
        <span @class(['badge', 'b-ok' => $live, 'b-contract' => ! $live])>{{ $live ? 'Tayang' : 'Menunggu verifikasi' }}</span>
        <h1 style="margin-top:12px">{{ $job->title }}</h1>
        <p>{{ $job->company->name }} · {{ $job->location }}</p>
    </div>

    <ol class="timeline">
        @foreach ($steps as [$title, $sub, $done])
            <li @class(['done' => $done])>
                <span class="dot" aria-hidden="true"><i class="ph {{ $done ? 'ph-check' : 'ph-hourglass-medium' }}"></i></span>
                <div><b>{{ $title }}</b><span>{{ $sub }}</span></div>
            </li>
        @endforeach
    </ol>

    @if ($live)
        <div class="row" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px">
            <a class="btn btn-accent" href="{{ route('jobs.index') }}">Lihat di Daftar Lowongan</a>
            <a class="btn btn-line" href="{{ route('jobs.posting.create') }}">Pasang Lowongan Lain</a>
        </div>
    @else
        <div class="formcard" style="margin-top:24px">
            <b>Apa selanjutnya?</b>
            <p class="demo-note" style="font-size:14px">Admin Kabelota mengecek bukti transfer Anda. Setelah disetujui, lowongan langsung tayang dan halaman ini berubah status. Simpan link halaman ini untuk mengecek.</p>
        </div>
    @endif
</div>
</x-layouts.app>
