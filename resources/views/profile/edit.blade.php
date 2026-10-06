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
    $skills = old('skills', $t?->skills ?? []);
    $errorStep = collect([
        1 => ['type', 'name', 'phone', 'email', 'city', 'bio', 'photo'],
        2 => ['concentration', 'gpa', 'graduation_year', 'experience_since', 'semester', 'thesis_topic', 'cv', 'transcript', 'skills'],
        3 => ['skk', 'projects', 'skk_scan'],
        4 => ['availability', 'preferred_locations', 'consent'],
    ])->search(fn ($keys) => collect($errors->keys())->contains(fn ($e) => collect($keys)->contains(fn ($k) => str_starts_with($e, $k))));
    $hasFile = fn ($col) => (bool) $t?->{$col};
@endphp
<x-layouts.app title="Profil Saya · Kabelota">
<div class="wrap" x-data="{
        step: {{ $errorStep ?: 1 }},
        steps: ['Data diri', 'Akademik & dokumen', 'Pengalaman', 'Ketersediaan'],
        type: @js($val('type', 'alumni')),
        name: @js($val('name')),
        city: @js($val('city')),
        bio: @js($val('bio')),
        conc: @js($val('concentration')),
        grad: @js($val('graduation_year')),
        since: @js($val('experience_since')),
        sem: @js($val('semester')),
        member: @js((bool) old('hmts_member', $t && $t->hmts_status !== 'pasif')),
        hmts: @js(old('hmts_status', $t && $t->hmts_status !== 'pasif' ? $t->hmts_status : '')),
        skills: @js(array_values($skills)),
        skk: @js(array_values($skk)),
        projects: @js(array_values($projects)),
        photo: @js($t?->photoUrl()),
        files: { cv: @js($hasFile('cv_path')), transcript: @js($hasFile('transcript_path')), skk_scan: @js($hasFile('skk_scan_path')) },
        go(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }) },
        initials() { return (this.name || 'Nama Anda').replace(/^(Muh\.|Moh\.)\s*/, '').split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase() },
        headline() {
            const j = this.skk.find(c => c.jabatan_kerja);
            if (this.type === 'alumni') return j ? j.jabatan_kerja : (this.conc ? 'Tenaga ahli ' + this.conc.toLowerCase() : 'Jabatan atau keahlian utama');
            return this.conc ? 'Mahasiswa Teknik Sipil, konsentrasi ' + this.conc.toLowerCase() : 'Mahasiswa Teknik Sipil';
        },
        topJenjang() { const l = this.skk.map(c => +c.jenjang).filter(Boolean); return l.length ? Math.max(...l) : null },
        years() { return this.since ? Math.max(0, {{ now()->year }} - +this.since) : null },
        checklist() {
            const alumni = this.type === 'alumni';
            return [
                ['Foto profil', !!this.photo],
                ['Ringkasan singkat', !!this.bio.trim()],
                ['CV (PDF)', this.files.cv],
                ['Keahlian software', this.skills.length > 0],
                [alumni ? 'Sertifikat SKK' : 'Transkrip nilai', alumni ? this.skk.some(c => c.jabatan_kerja) : this.files.transcript],
                [alumni ? 'Riwayat proyek' : 'Organisasi atau kerja praktik', this.projects.some(p => p.name)],
            ];
        },
        percent() { const c = this.checklist(); return Math.round(c.filter(i => i[1]).length / c.length * 100) },
        pickPhoto(e) { const f = e.target.files[0]; if (f) this.photo = URL.createObjectURL(f) },
    }">
    <div class="page-h">
        <h1>{{ $t ? 'Ubah profil' : 'Buat profil' }}</h1>
        <p>Profil ini yang dilihat perusahaan saat mencari talenta. Nomor HP, email, dan dokumen tetap tersembunyi sampai Anda menerima tawaran.</p>
    </div>

    <nav class="steps-nav" aria-label="Langkah">
        <template x-for="(label, i) in steps" :key="i">
            <button type="button" @click="go(i + 1)" :aria-current="step === i + 1 ? 'step' : null" :class="step > i + 1 && 'done'">
                <span class="mono" x-text="i + 1"></span> <span x-text="label"></span>
            </button>
        </template>
    </nav>

    @if ($errors->any())
        <div class="err-box" role="alert" style="margin-bottom:16px">Ada {{ $errors->count() }} isian yang perlu diperbaiki. Kolom yang bermasalah ditandai merah.</div>
    @endif

    <div class="mobile-progress" aria-hidden="true">
        <span>Kelengkapan profil</span><b class="mono" x-text="percent() + '%'"></b>
        <div class="bar"><span :style="'width:' + percent() + '%'"></span></div>
    </div>

    <div class="wizard">
    <form method="post" action="{{ route('profile.store') }}" enctype="multipart/form-data" class="formcard" novalidate>
        @csrf

        {{-- 1. Data diri --}}
        <div x-show="step === 1" class="stack" style="gap:20px">
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="fld-legend">Status saya sekarang</legend>
                <div class="status-pick">
                    <label :class="type === 'alumni' && 'on'"><input type="radio" name="type" value="alumni" x-model="type"><i class="ph ph-hard-hat" aria-hidden="true"></i><b>Alumni</b><span class="demo-note">Sudah lulus. Profil tampil sebagai tenaga ahli.</span></label>
                    <label :class="type === 'mahasiswa' && 'on'"><input type="radio" name="type" value="mahasiswa" x-model="type"><i class="ph ph-student" aria-hidden="true"></i><b>Mahasiswa</b><span class="demo-note">Masih kuliah. Profil diberi tanda Intern for Hire.</span></label>
                </div>
            </fieldset>
            <div class="photo-row">
                <label class="photo-pick" for="p-photo">
                    <template x-if="photo"><img :src="photo" alt="Pratinjau foto profil"></template>
                    <template x-if="!photo"><span class="mono" x-text="initials()"></span></template>
                </label>
                <div class="fld" style="flex:1">
                    <label for="p-photo">Foto profil <span class="demo-note">(opsional, disarankan)</span></label>
                    <input class="sr-only" id="p-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @change="pickPhoto">
                    <label for="p-photo" class="btn btn-line btn-sm @error('photo') is-err @enderror" style="justify-self:start"><i class="ph ph-camera" aria-hidden="true"></i> <span x-text="photo ? 'Ganti Foto' : 'Pilih Foto'">Pilih Foto</span></label>
                    <span class="demo-note">Foto wajah dengan latar polos, JPG atau PNG maksimal 5 MB. Otomatis dipotong persegi.</span>
                    @error('photo')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="fld"><label for="p-name">Nama lengkap dan gelar</label>
                <input class="box @error('name') is-err @enderror" id="p-name" name="name" x-model="name" placeholder="mis. Nurul Hikmah Saleh, S.T." autocomplete="name">
                @error('name')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="two">
                <div class="fld"><label for="p-phone">Nomor HP (WhatsApp)</label>
                    <input class="box @error('phone') is-err @enderror" id="p-phone" name="phone" value="{{ old('phone', $t?->phone) }}" inputmode="tel" autocomplete="tel" placeholder="08xx">
                    <span class="demo-note"><i class="ph ph-lock-simple" aria-hidden="true"></i> Tidak tampil di profil publik.</span>
                    @error('phone')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-email">Email</label>
                    <input class="box @error('email') is-err @enderror" id="p-email" name="email" type="email" value="{{ old('email', $t?->email) }}" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="fld"><label for="p-city">Domisili</label>
                <select class="box @error('city') is-err @enderror" id="p-city" name="city" x-model="city">
                    <option value="">Pilih kabupaten atau kota</option>
                    @foreach (config('kabelota.locations') as $l)<option>{{ $l }}</option>@endforeach
                </select>
                @error('city')<span class="err">{{ $message }}</span>@enderror</div>
            <fieldset class="hmts" style="margin:0">
                <legend class="fld-legend">Keanggotaan Himpunan Mahasiswa Teknik Sipil (HMTS)</legend>
                <input type="hidden" name="hmts_member" value="0">
                <label class="switch"><input type="checkbox" name="hmts_member" value="1" x-model="member"><span class="track" aria-hidden="true"></span> Saya terdaftar sebagai anggota HMTS</label>
                <div class="two" x-show="member" x-cloak style="margin-top:10px">
                    <div class="fld"><label for="p-hmts">Status keanggotaan</label>
                        <select class="box @error('hmts_status') is-err @enderror" id="p-hmts" name="hmts_status" x-model="hmts">
                            <option value="">Pilih status</option>
                            @foreach (collect(config('kabelota.hmts_statuses'))->except('pasif') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('hmts_status')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="p-hmts-pos">Jabatan <span class="demo-note">(opsional)</span></label>
                        <input class="box" id="p-hmts-pos" name="hmts_position" value="{{ old('hmts_position', $t?->hmts_position) }}" placeholder="mis. Koordinator Bidang Keilmuan 2025/2026"></div>
                </div>
                <p class="demo-note" x-show="!member" style="margin-top:6px">Kalau tidak dicentang, status keanggotaan tercatat sebagai Pasif.</p>
            </fieldset>
            <div class="fld"><label for="p-bio">Ringkasan singkat</label>
                <textarea class="box" id="p-bio" name="bio" rows="3" maxlength="500" x-model="bio" placeholder="mis. Berpengalaman di pengawasan jalan nasional, terbiasa dengan laporan MC dan kurva S."></textarea>
                <span class="demo-note"><span x-text="bio.length"></span>/500 karakter. Dua atau tiga kalimat sudah cukup.</span></div>
        </div>

        {{-- 2. Akademik & dokumen --}}
        <div x-show="step === 2" x-cloak class="stack" style="gap:20px">
            <div class="two">
                <div class="fld"><label for="p-conc">Konsentrasi</label>
                    <select class="box @error('concentration') is-err @enderror" id="p-conc" name="concentration" x-model="conc">
                        <option value="">Pilih konsentrasi</option>
                        @foreach (config('kabelota.concentrations') as $k)<option>{{ $k }}</option>@endforeach
                    </select>
                    @error('concentration')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-gpa">IPK <span class="demo-note">(opsional)</span></label>
                    <input class="box @error('gpa') is-err @enderror" id="p-gpa" name="gpa" inputmode="decimal" value="{{ $val('gpa') }}" placeholder="mis. 3.45">
                    @error('gpa')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="two" x-show="type === 'alumni'">
                <div class="fld"><label for="p-grad">Tahun lulus</label>
                    <input class="box @error('graduation_year') is-err @enderror" id="p-grad" name="graduation_year" inputmode="numeric" x-model="grad" placeholder="mis. 2019">
                    @error('graduation_year')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-since">Mulai bekerja di konstruksi</label>
                    <input class="box @error('experience_since') is-err @enderror" id="p-since" name="experience_since" inputmode="numeric" x-model="since" placeholder="mis. 2020">
                    <span class="demo-note" x-show="years() !== null" x-text="'Terhitung ' + years() + ' tahun pengalaman.'"></span>
                    @error('experience_since')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="two" x-show="type === 'mahasiswa'" x-cloak>
                <div class="fld"><label for="p-sem">Semester sekarang</label>
                    <input class="box @error('semester') is-err @enderror" id="p-sem" name="semester" inputmode="numeric" x-model="sem" placeholder="mis. 7">
                    @error('semester')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="p-ta">Topik tugas akhir <span class="demo-note">(opsional)</span></label>
                    <input class="box" id="p-ta" name="thesis_topic" value="{{ $val('thesis_topic') }}"></div>
            </div>

            <fieldset style="border:0;padding:0;margin:0">
                <legend class="fld-legend">Keahlian software dan alat</legend>
                <div class="chip-picks">
                    @foreach (config('kabelota.skills') as $skill)
                        <label :class="skills.includes(@js($skill)) && 'on'"><input type="checkbox" name="skills[]" value="{{ $skill }}" x-model="skills"> {{ $skill }}</label>
                    @endforeach
                </div>
            </fieldset>

            <div class="docs">
                <span class="fld-legend">Dokumen (PDF, maksimal 3 MB)</span>
                <x-doc-input name="cv" label="CV" hint="Riwayat hidup lengkap" :has="$hasFile('cv_path')" />
                <div x-show="type === 'mahasiswa'" x-cloak><x-doc-input name="transcript" label="Transkrip nilai" hint="Dari SIAKAD" :has="$hasFile('transcript_path')" /></div>
                <p class="demo-note"><i class="ph ph-lock-simple" aria-hidden="true"></i> Dokumen disimpan privat. Hanya perusahaan terverifikasi yang bisa mengunduh, dan Anda sendiri.</p>
            </div>
        </div>

        {{-- 3. Pengalaman --}}
        <div x-show="step === 3" x-cloak class="stack" style="gap:20px">
            <div x-show="type === 'alumni'" class="stack" style="gap:14px">
                <span class="fld-legend">Sertifikat SKK Konstruksi <span class="demo-note">(lewati kalau belum punya)</span></span>
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
                        <button type="button" class="linkbtn" x-show="skk.length > 1" @click="skk.splice(i, 1)"><i class="ph ph-trash" aria-hidden="true"></i> Hapus sertifikat ini</button>
                    </div>
                </template>
                @foreach ($errors->get('skk.*') as $msgs)<span class="err">{{ $msgs[0] }}</span>@endforeach
                <button type="button" class="btn btn-line btn-sm" style="justify-self:start" @click="skk.push({ jabatan_kerja: '', jenjang: '', registration_number: '', expires_at: '' })"><i class="ph ph-plus" aria-hidden="true"></i> Tambah SKK</button>
                <x-doc-input name="skk_scan" label="Scan SKK" hint="Gabungkan semua sertifikat dalam satu PDF" :has="$hasFile('skk_scan_path')" />
            </div>

            <div style="display:grid;gap:10px">
                <span class="fld-legend" x-text="type === 'alumni' ? 'Riwayat proyek' : 'Organisasi, asisten lab, atau kerja praktik'"></span>
                <template x-for="(p, i) in projects" :key="i">
                    <div class="rep">
                        <div class="fld"><label :for="'pr-n-' + i" x-text="type === 'alumni' ? 'Nama pekerjaan atau paket' : 'Nama kegiatan'"></label><input class="box" :id="'pr-n-' + i" :name="'projects[' + i + '][name]'" x-model="p.name" :placeholder="type === 'alumni' ? 'mis. Preservasi Jalan Ruas Tawaeli - Toboli' : 'mis. Asisten Laboratorium Mekanika Tanah'"></div>
                        <div class="three">
                            <div class="fld"><label :for="'pr-p-' + i">Posisi</label><input class="box" :id="'pr-p-' + i" :name="'projects[' + i + '][position]'" x-model="p.position"></div>
                            <div class="fld"><label :for="'pr-l-' + i">Lokasi</label><input class="box" :id="'pr-l-' + i" :name="'projects[' + i + '][location]'" x-model="p.location"></div>
                            <div class="fld"><label :for="'pr-y-' + i">Tahun</label><input class="box" inputmode="numeric" :id="'pr-y-' + i" :name="'projects[' + i + '][year_start]'" x-model="p.year_start"></div>
                        </div>
                        <div class="fld"><label :for="'pr-d-' + i">Uraian tugas <span class="demo-note">(opsional)</span></label><textarea class="box" rows="2" :id="'pr-d-' + i" :name="'projects[' + i + '][description]'" x-model="p.description"></textarea></div>
                        <button type="button" class="linkbtn" x-show="projects.length > 1" @click="projects.splice(i, 1)"><i class="ph ph-trash" aria-hidden="true"></i> Hapus</button>
                    </div>
                </template>
                @foreach ($errors->get('projects.*') as $msgs)<span class="err">{{ $msgs[0] }}</span>@endforeach
                <button type="button" class="btn btn-line btn-sm" style="justify-self:start" @click="projects.push({ name: '', position: '', location: '', year_start: '', description: '' })"><i class="ph ph-plus" aria-hidden="true"></i> Tambah pengalaman</button>
            </div>
        </div>

        {{-- 4. Ketersediaan --}}
        <div x-show="step === 4" x-cloak class="stack" style="gap:20px">
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
            <label class="consent"><input type="checkbox" name="consent" value="1" @checked(old('consent', (bool) $t))> Saya setuju data saya diproses sesuai Kebijakan Privasi Kabelota (UU No. 27 Tahun 2022). Kontak dan dokumen saya hanya dibuka untuk perusahaan yang tawarannya saya terima.</label>
            @error('consent')<span class="err">{{ $message }}</span>@enderror
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-line" x-show="step > 1" @click="go(step - 1)"><i class="ph ph-arrow-left" aria-hidden="true"></i> Sebelumnya</button>
            <span x-show="step === 1"></span>
            <button type="button" class="btn btn-ink" x-show="step < 4" @click="go(step + 1)">Lanjut <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
            <button type="submit" class="btn btn-accent" x-show="step === 4" x-cloak>Simpan dan Lihat Profil</button>
        </div>
    </form>

    <aside class="preview" aria-label="Pratinjau kartu talenta">
        <span class="lbl">Pratinjau di hasil pencarian</span>
        <article class="tcard" :class="type === 'mahasiswa' && 'intern'">
            <div class="top">
                <div class="av" aria-hidden="true">
                    <template x-if="photo"><img :src="photo" alt=""></template>
                    <template x-if="!photo"><span x-text="initials()"></span></template>
                </div>
                <div><h3 x-text="name || 'Nama Anda'"></h3><p class="role" x-text="headline()"></p></div>
            </div>
            <div class="tblock">
                <template x-if="type === 'alumni'"><div><span class="lbl">Jenjang</span><span class="v" x-text="topJenjang() || '-'"></span></div></template>
                <template x-if="type === 'alumni'"><div><span class="lbl">Pengalaman</span><span class="v" x-text="years() !== null ? years() + ' thn' : '-'"></span></div></template>
                <template x-if="type === 'mahasiswa'"><div><span class="lbl">Status</span><span class="v">Mhs</span></div></template>
                <template x-if="type === 'mahasiswa'"><div><span class="lbl">Semester</span><span class="v" x-text="sem || '-'"></span></div></template>
                <div><span class="lbl">Lokasi</span><span class="v" style="font-family:var(--f-body);font-weight:600" x-text="city || '-'"></span></div>
            </div>
            <div class="st">
                <span class="badge b-avail" x-show="type === 'alumni'">Tersedia</span>
                <span class="badge b-intern" x-show="type === 'mahasiswa'"><i class="ph ph-student" aria-hidden="true"></i> Intern for Hire</span>
            </div>
        </article>

        <div class="meter">
            <div class="meter-h"><b>Kelengkapan profil</b><span class="mono" x-text="percent() + '%'"></span></div>
            <div class="bar" role="progressbar" aria-label="Kelengkapan profil" :aria-valuenow="percent()" aria-valuemin="0" aria-valuemax="100"><span :style="'width:' + percent() + '%'"></span></div>
            <ul>
                <template x-for="[label, done] in checklist()" :key="label">
                    <li :class="done && 'done'"><i class="ph" :class="done ? 'ph-check-circle' : 'ph-circle'" aria-hidden="true"></i> <span x-text="label"></span></li>
                </template>
            </ul>
            <p class="demo-note">Profil yang lengkap lebih mudah dinilai HRD, terutama CV dan SKK.</p>
        </div>
    </aside>
    </div>
</div>
</x-layouts.app>
