@props(['name', 'label', 'hint' => null, 'has' => false])
<div class="doc" :class="files.{{ $name }} && 'ok'">
    <i class="ph" :class="typeof files.{{ $name }} === 'string' ? 'ph-check-circle' : (files.{{ $name }} ? 'ph-file-pdf' : 'ph-file-arrow-up')" aria-hidden="true"></i>
    <div>
        <label for="doc-{{ $name }}"><b>{{ $label }}</b></label>
        <span class="demo-note" x-text="files.{{ $name }} === true ? 'Sudah diunggah. Pilih file baru untuk mengganti.' : (files.{{ $name }} ? files.{{ $name }} + ' · siap dikirim saat disimpan' : @js($hint))"></span>
        @error($name)<span class="err">{{ $message }}</span>@enderror
    </div>
    <label class="btn btn-line btn-sm" for="doc-{{ $name }}" x-text="files.{{ $name }} ? 'Ganti' : 'Pilih PDF'">{{ $has ? 'Ganti' : 'Pilih PDF' }}</label>
    <input class="sr-only" id="doc-{{ $name }}" name="{{ $name }}" type="file" accept="application/pdf" @change="files.{{ $name }} = $event.target.files[0]?.name || files.{{ $name }}">
</div>
