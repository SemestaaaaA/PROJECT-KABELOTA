<x-layouts.app title="Tawaran Masuk · Kabelota">
<div class="wrap" style="max-width:900px" x-data="{ reject: null }">
    <div class="page-h">
        <h1>Tawaran masuk</h1>
        <p>Perusahaan yang ingin merekrut Anda. Nomor HP dan email baru terlihat oleh perusahaan setelah Anda menekan Terima.</p>
    </div>
    <x-page-tabs :items="[['Tawaran', 'talent.offers', auth()->user()->pendingOfferCount()], ['Lamaran Saya', 'talent.applications'], ['Profil', 'profile.edit']]" />

    @forelse ($offers as $offer)
        <article @class(['inbox-card', 'is-new' => $offer->status === 'menunggu'])>
            <div class="inbox-head">
                <div class="co-logo" aria-hidden="true">
                    @if ($offer->company?->logo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($offer->company->logo_path) }}" alt="">@else<i class="ph ph-buildings"></i>@endif
                </div>
                <div>
                    <h2>{{ $offer->position }}</h2>
                    <p class="demo-note">{{ $offer->company_name }} · dikirim {{ $offer->created_at->diffForHumans() }}</p>
                </div>
                <span class="badge {{ $offer->badgeClass() }}">{{ $offer->statusLabel() }}</span>
            </div>
            <dl class="inbox-meta">
                @if ($offer->start_date)<div><dt class="lbl">Mulai</dt><dd class="mono">{{ $offer->start_date->translatedFormat('j M Y') }}</dd></div>@endif
                @if ($offer->duration)<div><dt class="lbl">Durasi</dt><dd>{{ $offer->duration }}</dd></div>@endif
                <div><dt class="lbl">Kontak HRD</dt><dd>{{ $offer->contact_name }}</dd></div>
            </dl>
            <p class="inbox-msg">{{ $offer->message }}</p>

            @if ($offer->status === 'menunggu')
                <div class="inbox-actions">
                    <form method="post" action="{{ route('talent.offers.respond', $offer) }}">@csrf
                        <input type="hidden" name="decision" value="diterima">
                        <button class="btn btn-accent" type="submit"><i class="ph ph-check" aria-hidden="true"></i> Terima</button>
                    </form>
                    <button type="button" class="btn btn-line" @click="reject = {{ $offer->id }}">Tolak</button>
                    <span class="demo-note"><i class="ph ph-lock-simple" aria-hidden="true"></i> Menerima membuka nomor HP dan email Anda untuk {{ $offer->company_name }} saja.</span>
                </div>
                <form x-show="reject === {{ $offer->id }}" x-cloak class="reject-box" method="post" action="{{ route('talent.offers.respond', $offer) }}">@csrf
                    <input type="hidden" name="decision" value="ditolak">
                    <div class="fld"><label for="note-{{ $offer->id }}">Alasan <span class="demo-note">(opsional, dikirim ke perusahaan)</span></label>
                        <input class="box" id="note-{{ $offer->id }}" name="note" maxlength="300" placeholder="mis. Sedang terikat kontrak sampai Maret"></div>
                    <div style="display:flex;gap:8px"><button class="btn btn-ink btn-sm" type="submit">Kirim Penolakan</button><button type="button" class="btn btn-line btn-sm" @click="reject = null">Batal</button></div>
                </form>
            @else
                <p class="demo-note">Dijawab {{ $offer->responded_at?->diffForHumans() }}.@if ($offer->response_note) Catatan Anda: "{{ $offer->response_note }}"@endif
                    @if ($offer->status === 'diterima') Perusahaan akan menghubungi Anda lewat nomor atau email di profil.@endif</p>
            @endif
        </article>
    @empty
        <div class="empty" style="margin-top:20px">
            <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada tawaran</b>
            Lengkapi profil, SKK, dan CV supaya HRD lebih mudah menemukan Anda. <a class="textlink" href="{{ route('profile.edit') }}">Lengkapi profil</a>
        </div>
    @endforelse
</div>
</x-layouts.app>
