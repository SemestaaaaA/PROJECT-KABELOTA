@props(['record', 'position' => ''])
@php($key = $record->typeKey().'-'.$record->id)
<div x-data="{ open: @js(old('offer_key') === $key && $errors->any()) }" style="display:contents">
    <button type="button" class="btn btn-accent btn-sm" aria-haspopup="dialog" aria-label="Tawarkan kerja ke {{ \Illuminate\Support\Str::before($record->talent->name, ',') }}" @click="open = true"><i class="ph ph-handshake" aria-hidden="true"></i> Tawarkan kerja</button>
    <div class="modal-bg" x-show="open" x-cloak x-transition.opacity @click.self="open = false" @keydown.escape.window="open = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="jo-{{ $key }}" method="post"
            action="{{ route('company.job-offer.store', [$record->typeKey(), $record->id]) }}" x-trap.noscroll="open">@csrf
            <input type="hidden" name="offer_key" value="{{ $key }}">
            <header>
                <span class="lbl">Tawaran kerja final</span>
                <h2 id="jo-{{ $key }}">Tawarkan kerja ke {{ \Illuminate\Support\Str::before($record->talent->name, ',') }}</h2>
            </header>
            <div class="content">
                <p class="demo-note">Talenta punya {{ \App\Services\HiringDecision::OFFER_DAYS }} hari untuk menjawab. Kalau diambil, proses talenta dengan perusahaan lain ditutup otomatis.</p>
                <div class="fld"><label for="jp-{{ $key }}">Posisi</label>
                    <input class="box @error('job_offer_position') is-err @enderror" id="jp-{{ $key }}" name="job_offer_position" required maxlength="120" value="{{ old('offer_key') === $key ? old('job_offer_position') : $position }}">
                    @if (old('offer_key') === $key)@error('job_offer_position')<span class="err">{{ $message }}</span>@enderror @endif</div>
                <div class="fld"><label for="js-{{ $key }}">Tanggal mulai</label>
                    <input class="box @error('job_offer_start_date') is-err @enderror" id="js-{{ $key }}" type="date" name="job_offer_start_date" required min="{{ today()->toDateString() }}" value="{{ old('offer_key') === $key ? old('job_offer_start_date') : '' }}">
                    @if (old('offer_key') === $key)@error('job_offer_start_date')<span class="err">{{ $message }}</span>@enderror @endif</div>
                <div class="fld"><label for="jn-{{ $key }}">Catatan <span class="demo-note">(opsional: gaji, lokasi, dokumen yang dibawa)</span></label>
                    <textarea class="box @error('job_offer_note') is-err @enderror" id="jn-{{ $key }}" name="job_offer_note" rows="3" maxlength="500">{{ old('offer_key') === $key ? old('job_offer_note') : '' }}</textarea>
                    @if (old('offer_key') === $key)@error('job_offer_note')<span class="err">{{ $message }}</span>@enderror @endif</div>
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="open = false">Batal</button>
                <button class="btn btn-accent" type="submit">Kirim Tawaran Kerja</button>
            </footer>
        </form>
    </div>
</div>
