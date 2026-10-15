@props(['record', 'others'])
@php
    $key = $record->typeKey().'-'.$record->id;
    $closing = $others->reject(fn ($other) => $other->is($record));
@endphp
<div class="job-offer" x-data="{ take: false, decline: false }">
    <p class="lbl"><i class="ph ph-handshake" aria-hidden="true"></i> Tawaran kerja · berlaku {{ $record->jobOfferDaysLeft() }} hari lagi</p>
    <dl class="inbox-meta">
        <div><dt class="lbl">Posisi</dt><dd>{{ $record->job_offer_position }}</dd></div>
        @if ($record->job_offer_start_date)<div><dt class="lbl">Mulai</dt><dd class="mono">{{ $record->job_offer_start_date->translatedFormat('j M Y') }}</dd></div>@endif
        <div><dt class="lbl">Batas jawab</dt><dd class="mono">{{ $record->job_offer_expires_at->translatedFormat('j M Y') }}</dd></div>
    </dl>
    @if ($record->job_offer_note)<p class="inbox-msg">{{ $record->job_offer_note }}</p>@endif

    @if ($record->job_offer_expires_at->isPast())
        <p class="demo-note">Tawaran kerja ini sudah berakhir dan tidak bisa dijawab lagi.</p>
    @else
    <div class="inbox-actions">
        <button type="button" class="btn btn-accent" @click="take = true"><i class="ph ph-check" aria-hidden="true"></i> Ambil</button>
        <button type="button" class="btn btn-line" @click="decline = ! decline">Tolak</button>
    </div>

    <form x-show="decline" x-cloak class="reject-box" method="post" action="{{ route('talent.job-offer.decline', [$record->typeKey(), $record->id]) }}">@csrf
        <div class="fld"><label for="jd-{{ $key }}">Alasan <span class="demo-note">(opsional, dikirim ke perusahaan)</span></label>
            <input class="box" id="jd-{{ $key }}" name="note" maxlength="300" placeholder="mis. Lokasi terlalu jauh"></div>
        <div style="display:flex;gap:8px"><button class="btn btn-ink btn-sm" type="submit">Tolak Tawaran Kerja</button><button type="button" class="btn btn-line btn-sm" @click="decline = false">Batal</button></div>
    </form>

    <div class="modal-bg" x-show="take" x-cloak x-transition.opacity @click.self="take = false" @keydown.escape.window="take = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="jt-{{ $key }}" method="post"
            action="{{ route('talent.job-offer.accept', [$record->typeKey(), $record->id]) }}" x-trap.noscroll="take">@csrf
            <h2 id="jt-{{ $key }}">Ambil tawaran {{ $record->job_offer_position }}?</h2>
            <p style="margin-top:8px">{{ $record->companyName() }}@if ($record->job_offer_start_date), mulai {{ $record->job_offer_start_date->translatedFormat('j F Y') }}@endif.</p>
            @if ($closing->isNotEmpty())
                <p style="margin-top:14px">Proses Anda dengan perusahaan berikut akan <b>ditutup otomatis</b>:</p>
                <ul class="closing-list">
                    @foreach ($closing as $other)<li><b>{{ $other->companyName() }}</b> · {{ $other->title() }}@if ($other->companyId() === $record->companyId()) <span class="demo-note">(perusahaan yang sama)</span>@endif</li>@endforeach
                </ul>
                <div class="fld"><label for="jf-{{ $key }}">Pesan untuk perusahaan tersebut <span class="demo-note">(opsional, dikirim sekali)</span></label>
                    <textarea class="box" id="jf-{{ $key }}" name="farewell" rows="3" maxlength="500">Terima kasih atas kesempatannya. Saya sudah menerima tawaran kerja di tempat lain. Semoga kita bisa bekerja sama di proyek berikutnya.</textarea></div>
            @else
                <p style="margin-top:14px" class="demo-note">Tidak ada proses lain yang akan ditutup.</p>
            @endif
            <p class="demo-note" style="margin-top:10px">Status ketersediaan Anda akan menjadi <b>Terikat kontrak</b>. Anda bisa mengubahnya lagi di Profil.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                <button type="button" class="btn btn-line" @click="take = false">Batal</button>
                <button class="btn btn-accent" type="submit">Ya, ambil tawaran ini</button>
            </div>
        </form>
    </div>
    @endif
</div>
