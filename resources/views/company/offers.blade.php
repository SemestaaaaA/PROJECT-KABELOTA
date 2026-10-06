<x-layouts.app title="Tawaran Terkirim · Kabelota">
<div class="wrap" style="max-width:1000px">
    <div class="page-h">
        <h1>Tawaran terkirim</h1>
        <p>Kontak talenta terbuka di sini setelah talenta menerima tawaran Anda.</p>
    </div>
    <x-page-tabs :items="[['Lowongan Saya', 'company.jobs'], ['Tawaran Terkirim', 'company.offers'], ['Profil Perusahaan', 'company.profile']]" />

    @if ($offers->isEmpty())
        <div class="empty" style="margin-top:20px">
            <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada tawaran</b>
            Cari talenta lalu tekan Ajukan Rekrut di profilnya. <a class="textlink" href="{{ route('talents.index') }}">Cari talenta</a>
        </div>
    @else
        <div class="tlist-wrap" style="margin-top:20px">
            <table class="tlist">
                <thead><tr><th>Talenta</th><th>Posisi</th><th>Status</th><th>Kontak</th><th>Dikirim</th></tr></thead>
                <tbody>
                @foreach ($offers as $offer)
                    <tr>
                        <td><a href="{{ route('talents.show', $offer->talent) }}"><b>{{ $offer->talent->name }}</b></a><div class="demo-note">{{ $offer->talent->headline }}</div></td>
                        <td>{{ $offer->position }}</td>
                        <td><span class="badge {{ $offer->badgeClass() }}">{{ $offer->statusLabel() }}</span>
                            @if ($offer->response_note)<div class="demo-note" style="margin-top:4px">"{{ $offer->response_note }}"</div>@endif</td>
                        <td>
                            @if ($offer->status === 'diterima')
                                <div class="contact-reveal">
                                    <a href="tel:{{ $offer->talent->phone }}" class="mono"><i class="ph ph-phone" aria-hidden="true"></i> {{ $offer->talent->phone }}</a>
                                    <a href="mailto:{{ $offer->talent->email }}" class="mono"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $offer->talent->email }}</a>
                                </div>
                            @else
                                <span class="demo-note"><i class="ph ph-lock-simple" aria-hidden="true"></i> {{ $offer->status === 'menunggu' ? 'Terbuka setelah diterima' : 'Tetap tersembunyi' }}</span>
                            @endif
                        </td>
                        <td class="mono" style="white-space:nowrap">{{ $offer->created_at->translatedFormat('j M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</x-layouts.app>
