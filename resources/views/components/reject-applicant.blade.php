@props(['application'])
@php($key = 'rj-'.$application->id)
<div x-data="{ open: @js(old('reject_key') === $key && $errors->any()), reason: @js(old('reject_key') === $key ? old('reason', '') : '') }" style="display:contents">
    <button type="button" class="btn btn-line btn-sm" aria-haspopup="dialog" aria-label="Tolak lamaran {{ \Illuminate\Support\Str::before($application->talent->name, ',') }}" @click="open = true"><i class="ph ph-x-circle" aria-hidden="true"></i> Tolak</button>
    <div class="modal-bg" x-show="open" x-cloak x-transition.opacity @click.self="open = false" @keydown.escape.window="open = false">
        <form class="sheet" role="dialog" aria-modal="true" aria-labelledby="{{ $key }}-h" method="post" action="{{ route('company.applications.update', $application) }}" x-trap.noscroll="open">@csrf
            <input type="hidden" name="status" value="ditolak">
            <input type="hidden" name="reject_key" value="{{ $key }}">
            <header>
                <span class="lbl">Tolak pelamar</span>
                <h2 id="{{ $key }}-h">Tolak lamaran {{ \Illuminate\Support\Str::before($application->talent->name, ',') }}?</h2>
            </header>
            <div class="content">
                <p class="demo-note">Talenta menerima email pemberitahuan. Selama belum ada tawaran kerja, Anda masih bisa meninjau ulang lamaran ini.</p>
                <fieldset class="reason-list">
                    <legend class="fld-legend">Alasan <span class="demo-note">(opsional, ikut di email)</span></legend>
                    @foreach (\App\Models\JobApplication::REJECT_REASONS as $i => $reason)
                        <label class="reason-opt"><input type="radio" name="reason" value="{{ $reason }}" x-model="reason"> {{ $reason }}</label>
                    @endforeach
                    @if (old('reject_key') === $key)@error('reason')<span class="err">{{ $message }}</span>@enderror @endif
                </fieldset>
                <div class="fld"><label for="{{ $key }}-n">Catatan untuk talenta <span class="demo-note">(opsional)</span></label>
                    <textarea class="box @error('note') is-err @enderror" id="{{ $key }}-n" name="note" rows="3" maxlength="300" :placeholder="reason === 'Lainnya' ? 'Tulis alasannya di sini' : 'mis. Terima kasih sudah melamar, semoga sukses.'">{{ old('reject_key') === $key ? old('note') : '' }}</textarea>
                    @if (old('reject_key') === $key)@error('note')<span class="err">{{ $message }}</span>@enderror @endif</div>
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="open = false">Batal</button>
                <button class="btn btn-ink" type="submit">Ya, tolak pelamar</button>
            </footer>
        </form>
    </div>
</div>
