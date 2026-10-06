@props(['name', 'id', 'label', 'accept', 'placeholder', 'hint', 'current' => null, 'icon' => 'ph-upload-simple'])
{{-- Upload box that confirms the chosen file: green frame, check icon, size, and a thumbnail for images. --}}
<div class="fld" x-data="{
        file: null, url: null,
        pick(e) {
            const f = e.target.files[0];
            if (!f) return;
            if (this.url) URL.revokeObjectURL(this.url);
            this.file = { name: f.name, size: f.size < 1048576 ? Math.max(1, Math.round(f.size / 1024)) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB' };
            this.url = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
        },
    }">
    <label for="{{ $id }}">{{ $label }}</label>
    <label @class(['drop', 'is-err' => $errors->has($name), 'has-current' => $current]) :class="file && 'ok'" for="{{ $id }}">
        <template x-if="url"><img class="drop-thumb" :src="url" alt="Pratinjau file yang dipilih"></template>
        <i class="ph" :class="file ? 'ph-check-circle' : '{{ $current ? 'ph-file-text' : $icon }}'" aria-hidden="true"></i>
        <span class="drop-name" x-text="file ? file.name : @js($current ? 'Sudah diunggah' : $placeholder)"></span>
        <small x-text="file ? file.size + ' · siap dikirim saat disimpan. Klik untuk mengganti.' : @js($current ? 'Pilih file baru untuk mengganti. '.$hint : $hint)"></small>
    </label>
    <input class="sr-only" id="{{ $id }}" name="{{ $name }}" type="file" accept="{{ $accept }}" @change="pick($event)">
    <p class="sr-only" aria-live="polite" x-text="file ? 'File ' + file.name + ' dipilih' : ''"></p>
    @error($name)<span class="err">{{ $message }}</span>@enderror
</div>
