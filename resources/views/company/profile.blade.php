@php($c = $company)
<x-layouts.app title="Profil Perusahaan · Kabelota">
<div class="wrap" style="max-width:860px">
    <div class="page-h">
        <span @class(['badge', 'b-ok' => $c->isVerified(), 'b-contract' => $c->status === 'menunggu', 'b-bad' => $c->status === 'ditolak'])>{{ $c->statusLabel() }}</span>
        <h1 style="margin-top:12px">Profil perusahaan</h1>
        <p>Profil ini tampil di setiap lowongan dan tawaran yang Anda kirim ke talenta.</p>
    </div>
    <x-page-tabs :items="[['Lowongan Saya', 'company.jobs'], ['Tawaran Terkirim', 'company.offers'], ['Profil Perusahaan', 'company.profile']]" />

    @if ($c->status === 'ditolak')
        <div class="err-box" role="alert" style="margin-top:20px"><b>Verifikasi ditolak.</b> {{ $c->rejection_reason ?? 'Silakan periksa kembali dokumen Anda.' }} Perbaiki data lalu simpan untuk mengajukan ulang.</div>
    @elseif ($c->status === 'menunggu')
        <div class="locked" style="margin-top:20px"><b>Menunggu verifikasi admin</b>Setelah NIB atau SBU dicek, Anda bisa mengajukan rekrut, membuka CV talenta, dan memasang lowongan.</div>
    @endif

    <form class="formcard" method="post" action="{{ route('company.profile.update') }}" enctype="multipart/form-data" style="margin-top:20px" novalidate>
        @csrf
        <div class="photo-row" x-data="{ logo: @js($c->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->logo_path) : null) }">
            <label class="photo-pick" for="c-logo" style="background:var(--surface);color:var(--ink)">
                <template x-if="logo"><img :src="logo" alt="Logo perusahaan"></template>
                <template x-if="!logo"><i class="ph ph-buildings" aria-hidden="true"></i></template>
            </label>
            <div class="fld" style="flex:1"><label for="c-logo">Logo <span class="demo-note">(opsional)</span></label>
                <input class="box" id="c-logo" name="logo" type="file" accept="image/*" @change="const f = $event.target.files[0]; if (f) logo = URL.createObjectURL(f)">
                @error('logo')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <div class="two">
            <div class="fld"><label for="c-name">Nama perusahaan</label>
                <input class="box @error('name') is-err @enderror" id="c-name" name="name" value="{{ old('name', $c->name) }}">
                @error('name')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="fld"><label for="c-type">Jenis</label>
                <select class="box" id="c-type" name="type">
                    @foreach (['konsultan' => 'Konsultan', 'kontraktor' => 'Kontraktor', 'pemilik_proyek' => 'Pemilik proyek / instansi', 'lainnya' => 'Lainnya'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('type', $c->type) === $k)>{{ $label }}</option>
                    @endforeach
                </select></div>
        </div>
        <div class="two">
            <div class="fld"><label for="c-nib">NIB</label>
                <input class="box mono @error('nib') is-err @enderror" id="c-nib" name="nib" value="{{ old('nib', $c->nib) }}" inputmode="numeric">
                @error('nib')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="fld"><label for="c-city">Kota</label>
                <select class="box" id="c-city" name="city">
                    @foreach (config('kabelota.locations') as $l)<option @selected(old('city', $c->city) === $l)>{{ $l }}</option>@endforeach
                </select></div>
        </div>
        <div class="fld"><label for="c-about">Profil singkat <span class="demo-note">(opsional)</span></label>
            <textarea class="box" id="c-about" name="about" rows="3" maxlength="800" placeholder="Bidang usaha, jenis proyek yang biasa dikerjakan, wilayah kerja.">{{ old('about', $c->about) }}</textarea></div>
        <div class="three">
            <div class="fld"><label for="c-cn">Penanggung jawab rekrutmen</label>
                <input class="box @error('contact_name') is-err @enderror" id="c-cn" name="contact_name" value="{{ old('contact_name', $c->contact_name) }}">
                @error('contact_name')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="fld"><label for="c-cp">Nomor HP</label>
                <input class="box @error('contact_phone') is-err @enderror" id="c-cp" name="contact_phone" value="{{ old('contact_phone', $c->contact_phone) }}" inputmode="tel">
                @error('contact_phone')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="fld"><label for="c-web">Website <span class="demo-note">(opsional)</span></label>
                <input class="box @error('website') is-err @enderror" id="c-web" name="website" value="{{ old('website', $c->website) }}" placeholder="https://">
                @error('website')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <div class="fld" x-data="{ file: @js($c->legal_doc_path ? 'Sudah diunggah. Pilih file baru untuk mengganti.' : '') }">
            <label for="c-legal">Dokumen legalitas (NIB atau SBU, PDF maks. 3 MB)</label>
            <label class="drop @error('legal_doc') is-err @enderror" for="c-legal"><i class="ph ph-file-arrow-up" aria-hidden="true"></i><span x-text="file || 'Pilih PDF'"></span><small>Hanya admin Kabelota yang bisa melihat dokumen ini.</small></label>
            <input class="sr-only" id="c-legal" name="legal_doc" type="file" accept="application/pdf" @change="file = $event.target.files[0]?.name || file">
            @error('legal_doc')<span class="err">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-accent" type="submit" style="justify-self:start">{{ $c->isVerified() ? 'Simpan' : 'Simpan dan Ajukan Verifikasi' }}</button>
    </form>
</div>
</x-layouts.app>
