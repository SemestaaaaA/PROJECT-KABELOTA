@php
    $t = $talent;
    $val = fn ($key, $default = '') => old($key, $t?->{$key} ?? $default);
    $skk = old('skk', $t?->certifications->map(fn ($c) => [
        'jabatan_kerja' => $c->jabatan_kerja, 'jenjang' => $c->jenjang,
        'registration_number' => $c->registration_number, 'expires_at' => $c->expires_at->toDateString(),
    ])->all() ?: [['jabatan_kerja' => '', 'jenjang' => '', 'registration_number' => '', 'expires_at' => '']]);
    $projects = old('projects', $t?->projects->map(fn ($p) => [
        'name' => $p->name, 'position' => $p->position, 'location' => $p->location,
        'year_start' => $p->year_start, 'description' => $p->description,
    ])->all() ?: [['name' => '', 'position' => '', 'location' => '', 'year_start' => '', 'description' => '']]);
    $prefs = old('preferred_locations', $t?->preferred_locations ?? []);
    // Jump to the first step that has an error after a failed submit.
    $errorStep = collect([
        1 => ['type', 'name', 'phone', 'email', 'city', 'bio'],
        2 => ['concentration', 'gpa', 'graduation_year', 'experience_since', 'semester', 'thesis_topic'],
        3 => ['skk', 'projects'],
        4 => ['availability', 'preferred_locations', 'consent'],
    ])->search(fn ($keys) => collect($errors->keys())->contains(fn ($e) => collect($keys)->contains(fn ($k) => str_starts_with($e, $k))));
@endphp
<x-layouts.app title="Profil Saya · Kabelota">
<div class="wrap" x-data="{
        step: {{ $errorStep ?: 1 }},
        type: @js($val('type', 'alumni')),
        skk: @js(array_values($skk)),
        projects: @js(array_values($projects)),
        steps: ['Data diri', 'Akademik', 'Pengalaman', 'Ketersediaan'],
        go(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }) },
    }">
    <div class="page-h">
        <h1>{{ $t ? 'Ubah profil' : 'Buat profil' }}</h1>
        <p>Profil ini yang dilihat perusahaan saat mencari talenta. Nomor HP dan email tetap tersembunyi sampai Anda menerima tawaran.</p>
    </div>

    <nav class="steps-nav" aria-label="Langkah">
        <template x-for="(label, i) in steps" :key="i">
            <button type="button" @click="go(i + 1)" :aria-current="step === i + 1 ? 'step' : null" :class="step > i + 1 && 'done'" x-text="(i + 1) + '. ' + label"></button>
        </template>
    </nav>

    @if ($errors->any())
        <div class="err-box" role="alert" style="margin-bottom:16px">Ada {{ $errors->count() }} isian yang perlu diperbaiki. Kolom yang bermasalah ditandai merah.</div>
    @endif

    <form method="post" action="{{ route('profile.store') }}" class="formcard" style="max-width:820px" novalidate>
        @csrf

        {{-- 1. Data diri --}}
        <div x-show="step === 1" style="display:grid;gap:14px">
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="fld-legend">Status saya sekarang</legend>
                <div class="status-pick">
                    <label :class="type === 'alumni' && 'on'"><input type="radio" name="type" value="alumni" x-model="type"><i class="ph ph-hard-hat" aria-hidden="true"></i><b>Alumni</b><span class="demo-note">Sudah lulus. Profil tampil sebagai tenaga ahli.</span></label>
                    <label :class="type === 'mahasiswa' && 'on'"><input type="radio" name="type" value="mahasiswa" x-model="type"><i class="ph ph-student" aria-hidden="true"></i><b>Mahasiswa</b><span class="demo-note">Masih kuliah. Profil diberi tanda Intern for Hire.</span></label>
                </div>
            </fieldset>
            <div class="fld"><label for="p-name">Nama lengkap dan gelar</label>
                <input class="box @error('name') is-err @enderror" id="p-name" name="name" value="{{ $val('name') }}" placeholder="mis. Nurul Hikmah Saleh, S.T." autocomplete="name">
                @error('name')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="two">
                <div class="fld"><label for="p-phone">Nomor HP (WhatsApp)</label>
                    <input class="box @error('phone') is-err @enderror" id="p-phone" name="phone" value="{{ old('phone', $t?->phone) }}" inputmode="tel" autocomplete="tel">
                    <span class="demo-note">Tidak pernah tampil di profil publik.</span>
                    @error('phone')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-email">Email</label>
                    <input class="box @error('email') is-err @enderror" id="p-email" name="email" type="email" value="{{ old('email', $t?->email) }}" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="fld"><label for="p-city">Domisili</label>
                <select class="box @error('city') is-err @enderror" id="p-city" name="city">
                    <option value="">Pilih kabupaten atau kota</option>
                    @foreach (config('kabelota.locations') as $l)<option @selected($val('city') === $l)>{{ $l }}</option>@endforeach
                </select>
                @error('city')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="fld"><label for="p-bio">Ringkasan singkat <span class="demo-note">(opsional)</span></label>
                <textarea class="box" id="p-bio" name="bio" rows="3" maxlength="500" placeholder="mis. Berpengalaman di pengawasan jalan nasional, terbiasa dengan laporan MC dan kurva S.">{{ $val('bio') }}</textarea></div>
        </div>

        {{-- 2. Akademik --}}
        <div x-show="step === 2" x-cloak style="display:grid;gap:14px">
            <div class="two">
                <div class="fld"><label for="p-conc">Konsentrasi</label>
                    <select class="box @error('concentration') is-err @enderror" id="p-conc" name="concentration">
                        <option value="">Pilih konsentrasi</option>
                        @foreach (config('kabelota.concentrations') as $k)<option @selected($val('concentration') === $k)>{{ $k }}</option>@endforeach
                    </select>
                    @error('concentration')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-gpa">IPK <span class="demo-note">(opsional)</span></label>
                    <input class="box @error('gpa') is-err @enderror" id="p-gpa" name="gpa" inputmode="decimal" value="{{ $val('gpa') }}" placeholder="mis. 3.45">
                    @error('gpa')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="two" x-show="type === 'alumni'">
                <div class="fld"><label for="p-grad">Tahun lulus</label>
                    <input class="box @error('graduation_year') is-err @enderror" id="p-grad" name="graduation_year" inputmode="numeric" value="{{ $val('graduation_year') }}" placeholder="mis. 2019">
                    @error('graduation_year')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-since">Mulai bekerja di bidang konstruksi</label>
                    <input class="box @error('experience_since') is-err @enderror" id="p-since" name="experience_since" inputmode="numeric" value="{{ $val('experience_since') }}" placeholder="mis. 2020">
                    <span class="demo-note">Dipakai untuk menghitung lama pengalaman.</span>
                    @error('experience_since')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="two" x-show="type === 'mahasiswa'" x-cloak>
                <div class="fld"><label for="p-sem">Semester sekarang</label>
                    <input class="box @error('semester') is-err @enderror" id="p-sem" name="semester" inputmode="numeric" value="{{ $val('semester') }}" placeholder="mis. 7">
                    @error('semester')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-ta">Topik tugas akhir <span class="demo-note">(opsional)</span></label>
                    <input class="box" id="p-ta" name="thesis_topic" value="{{ $val('thesis_topic') }}"></div>
            </div>
            <div class="fld"><label for="p-cv">CV dan transkrip (PDF)</label>
                <input class="box" id="p-cv" type="file" accept="application/pdf" disabled>
                <span class="demo-note">Unggah dokumen aktif saat launch. File disimpan privat dan hanya bisa diunduh perusahaan terverifikasi.</span></div>
        </div>

        {{-- 3. Pengalaman --}}
        <div x-show="step === 3" x-cloak style="display:grid;gap:14px">
            <div x-show="type === 'alumni'" style="display:grid;gap:10px">
                <span class="fld-legend">Sertifikat SKK Konstruksi <span class="demo-note">(kosongkan kalau belum punya)</span></span>
                <template x-for="(c, i) in skk" :key="i">
                    <div class="rep">
                        <div class="two">
                            <div class="fld"><label :for="'skk-j-' + i">Jabatan kerja</label>
                                <select class="box" :id="'skk-j-' + i" :name="'skk[' + i + '][jabatan_kerja]'" x-model="c.jabatan_kerja">
                                    <option value="">Pilih jabatan kerja</option>
                                    @foreach (array_keys(config('kabelota.jabatan_kerja')) as $j)<option>{{ $j }}</option>@endforeach
                                </select></div>
                            <div class="fld"><label :for="'skk-l-' + i">Jenjang</label>
                                <select class="box" :id="'skk-l-' + i" :name="'skk[' + i + '][jenjang]'" x-model="c.jenjang">
                                    <option value="">Pilih jenjang</option>
                                    @foreach (config('kabelota.jenjang') as $lvl => $label)<option value="{{ $lvl }}">{{ $lvl }} · {{ $label }}</option>@endforeach
                                </select></div>
                        </div>
                        <div class="two">
                            <div class="fld"><label :for="'skk-r-' + i">Nomor registrasi</label><input class="box mono" :id="'skk-r-' + i" :name="'skk[' + i + '][registration_number]'" x-model="c.registration_number"></div>
                            <div class="fld"><label :for="'skk-e-' + i">Berlaku sampai</label><input class="box" type="date" :id="'skk-e-' + i" :name="'skk[' + i + '][expires_at]'" x-model="c.expires_at"></div>
                        </div>
                        <button type="button" class="textlink" style="justify-self:start;background:none;border:0;padding:0;cursor:pointer;color:var(--ink)" x-show="skk.length > 1" @click="skk.splice(i, 1)">Hapus sertifikat ini</button>
                    </div>
                </template>
                @foreach ($errors->get('skk.*') as $msgs)<span class="err">{{ $msgs[0] }}</span>@endforeach
                <button type="button" class="btn btn-line btn-sm" style="justify-self:start" @click="skk.push({ jabatan_kerja: '', jenjang: '', registration_number: '', expires_at: '' })"><i class="ph ph-plus" aria-hidden="true"></i> Tambah SKK</button>
            </div>

            <div style="display:grid;gap:10px">
                <span class="fld-legend" x-text="type === 'alumni' ? 'Riwayat proyek' : 'Organisasi, asisten lab, atau kerja praktik'">Riwayat proyek</span>
                <template x-for="(p, i) in projects" :key="i">
                    <div class="rep">
                        <div class="fld"><label :for="'pr-n-' + i" x-text="type === 'alumni' ? 'Nama pekerjaan atau paket' : 'Nama kegiatan'"></label><input class="box" :id="'pr-n-' + i" :name="'projects[' + i + '][name]'" x-model="p.name"></div>
                        <div class="three">
                            <div class="fld"><label :for="'pr-p-' + i">Posisi</label><input class="box" :id="'pr-p-' + i" :name="'projects[' + i + '][position]'" x-model="p.position"></div>
                            <div class="fld"><label :for="'pr-l-' + i">Lokasi</label><input class="box" :id="'pr-l-' + i" :name="'projects[' + i + '][location]'" x-model="p.location"></div>
                            <div class="fld"><label :for="'pr-y-' + i">Tahun</label><input class="box" inputmode="numeric" :id="'pr-y-' + i" :name="'projects[' + i + '][year_start]'" x-model="p.year_start"></div>
                        </div>
                        <div class="fld"><label :for="'pr-d-' + i">Uraian tugas <span class="demo-note">(opsional)</span></label><textarea class="box" rows="2" :id="'pr-d-' + i" :name="'projects[' + i + '][description]'" x-model="p.description"></textarea></div>
                        <button type="button" class="textlink" style="justify-self:start;background:none;border:0;padding:0;cursor:pointer;color:var(--ink)" x-show="projects.length > 1" @click="projects.splice(i, 1)">Hapus</button>
                    </div>
                </template>
                @foreach ($errors->get('projects.*') as $msgs)<span class="err">{{ $msgs[0] }}</span>@endforeach
                <button type="button" class="btn btn-line btn-sm" style="justify-self:start" @click="projects.push({ name: '', position: '', location: '', year_start: '', description: '' })"><i class="ph ph-plus" aria-hidden="true"></i> Tambah pengalaman</button>
            </div>
        </div>

        {{-- 4. Ketersediaan --}}
        <div x-show="step === 4" x-cloak style="display:grid;gap:16px">
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="fld-legend">Status ketersediaan</legend>
                <div class="checks">
                    @foreach (\App\Enums\Availability::cases() as $a)
                        <label><input type="radio" name="availability" value="{{ $a->value }}" @checked(old('availability', $t?->availability?->value ?? 'tersedia') === $a->value)> {{ $a->label() }}</label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="fld-legend">Bersedia ditempatkan di</legend>
                <div class="checks cols">
                    @foreach (config('kabelota.locations') as $l)
                        <label><input type="checkbox" name="preferred_locations[]" value="{{ $l }}" @checked(in_array($l, $prefs))> {{ $l }}</label>
                    @endforeach
                </div>
                @error('preferred_locations')<span class="err">{{ $message }}</span>@enderror
            </fieldset>
            <label class="consent"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> Saya setuju data saya diproses sesuai Kebijakan Privasi Kabelota (UU No. 27 Tahun 2022). Kontak saya hanya dibuka untuk perusahaan yang tawarannya saya terima.</label>
            @error('consent')<span class="err">{{ $message }}</span>@enderror
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-line" x-show="step > 1" @click="go(step - 1)">Sebelumnya</button>
            <span x-show="step === 1"></span>
            <button type="button" class="btn btn-ink" x-show="step < 4" @click="go(step + 1)">Lanjut</button>
            <button type="submit" class="btn btn-accent" x-show="step === 4" x-cloak>Simpan dan Lihat Profil</button>
        </div>
    </form>
</div>
</x-layouts.app>
