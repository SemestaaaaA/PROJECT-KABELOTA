@php
    $pkgs = config('kabelota.packages');
    $errorStep = collect([1 => ['title', 'concentration', 'min_jenjang', 'min_experience', 'duration_months', 'location', 'description'], 2 => ['package'], 3 => ['payment_proof']])
        ->search(fn ($keys) => collect($errors->keys())->intersect($keys)->isNotEmpty());
@endphp
<x-layouts.app title="Pasang Lowongan · Kabelota">
<div class="wrap" x-data="{
        step: {{ $errorStep ?: 1 }},
        steps: ['Detail lowongan', 'Pilih paket', 'Pembayaran'],
        f: { title: @js(old('title', '')), location: @js(old('location', 'Palu')), duration: @js(old('duration_months', '6')), jenjang: @js(old('min_jenjang', '')), exp: @js(old('min_experience', '')) },
        pkg: @js(old('package', 'reguler')),
        pkgs: @js($pkgs),
        go(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }) },
        rupiah(n) { return 'Rp' + Number(n).toLocaleString('id-ID') },
    }">
    <div class="page-h">
        <h1>Pasang lowongan</h1>
        <p>Atas nama <b>{{ $company->name }}</b>. Lowongan tayang setelah admin mengecek bukti transfer.</p>
    </div>

    <nav class="steps-nav" aria-label="Langkah">
        <template x-for="(label, i) in steps" :key="i">
            <button type="button" @click="go(i + 1)" :aria-current="step === i + 1 ? 'step' : null" :class="step > i + 1 && 'done'" x-text="(i + 1) + '. ' + label"></button>
        </template>
    </nav>

    @if ($errors->any())
        <div class="err-box" role="alert" style="margin-bottom:16px">Ada isian yang perlu diperbaiki. Kolom yang bermasalah ditandai merah.</div>
    @endif

    <div class="wizard">
        <form method="post" action="{{ route('jobs.posting.store') }}" enctype="multipart/form-data" class="formcard" novalidate>
            @csrf
            <div x-show="step === 1" class="stack" style="gap:20px">
                <div class="fld"><label for="j-title">Judul posisi</label>
                    <input class="box @error('title') is-err @enderror" id="j-title" name="title" x-model="f.title" placeholder="mis. Site Engineer Jalan, Paket Preservasi Ruas Tawaeli - Toboli">
                    @error('title')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="three">
                    <div class="fld"><label for="j-loc">Lokasi</label>
                        <select class="box" id="j-loc" name="location" x-model="f.location">
                            @foreach (config('kabelota.locations') as $l)<option>{{ $l }}</option>@endforeach
                        </select></div>
                    <div class="fld"><label for="j-dur">Durasi kontrak (bulan)</label>
                        <input class="box @error('duration_months') is-err @enderror" id="j-dur" name="duration_months" inputmode="numeric" x-model="f.duration">
                        @error('duration_months')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="j-conc">Konsentrasi</label>
                        <select class="box" id="j-conc" name="concentration">
                            <option value="">Semua</option>
                            @foreach (config('kabelota.concentrations') as $k)<option @selected(old('concentration') === $k)>{{ $k }}</option>@endforeach
                        </select></div>
                </div>
                <div class="two">
                    <div class="fld"><label for="j-jen">Jenjang SKK minimal</label>
                        <select class="box" id="j-jen" name="min_jenjang" x-model="f.jenjang">
                            <option value="">Tidak disyaratkan</option>
                            @foreach (config('kabelota.jenjang') as $lvl => $label)<option value="{{ $lvl }}">{{ $lvl }} · {{ $label }}</option>@endforeach
                        </select></div>
                    <div class="fld"><label for="j-exp">Pengalaman minimal (tahun)</label>
                        <input class="box" id="j-exp" name="min_experience" inputmode="numeric" x-model="f.exp" placeholder="Kosongkan kalau untuk fresh graduate"></div>
                </div>
                <div class="fld"><label for="j-desc">Deskripsi pekerjaan</label>
                    <textarea class="box @error('description') is-err @enderror" id="j-desc" name="description" rows="5" placeholder="Tugas utama, lokasi penempatan, fasilitas (mess, transport), dokumen yang perlu disiapkan.">{{ old('description') }}</textarea>
                    @error('description')<span class="err">{{ $message }}</span>@enderror</div>
            </div>

            <div x-show="step === 2" x-cloak class="stack" style="gap:14px">
                <span class="fld-legend">Pilih paket tayang</span>
                @foreach ($pkgs as $key => $pkg)
                    <label class="pkg" :class="pkg === '{{ $key }}' && 'on'">
                        <input type="radio" name="package" value="{{ $key }}" x-model="pkg">
                        <span><b>{{ $key === 'tenaga_ahli' ? 'Tenaga Ahli / Tender' : $pkg['label'] }}</b>
                            <small>{{ ['magang' => 'Untuk mahasiswa semester akhir. Tampil dengan tanda magang.', 'reguler' => 'Posisi umum di proyek.', 'tenaga_ahli' => 'Tampil paling atas dengan strip kuning. Cocok untuk kebutuhan tender.'][$key] }}</small></span>
                        <span class="mono amt">Rp{{ number_format($pkg['price'], 0, ',', '.') }}<small>{{ $pkg['days'] }} hari</small></span>
                    </label>
                @endforeach
            </div>

            <div x-show="step === 3" x-cloak class="stack" style="gap:20px">
                <div class="bank">
                    <span class="lbl">Transfer ke</span>
                    <b>{{ config('kabelota.bank.name') }} <span class="mono">{{ config('kabelota.bank.number') }}</span></b>
                    <span>a.n. {{ config('kabelota.bank.holder') }}</span>
                    <span class="lbl" style="margin-top:8px">Jumlah</span>
                    <b class="mono" style="font-size:24px" x-text="rupiah(pkgs[pkg].price)"></b>
                </div>
                <x-file-drop name="payment_proof" id="j-proof" label="Bukti transfer" accept="image/jpeg,image/png,application/pdf"
                    placeholder="Pilih foto atau PDF bukti transfer" hint="JPG, PNG, atau PDF, maksimal 2 MB" />
                <p class="demo-note">Mode demo: unggah gambar apa saja. Rekening di atas masih placeholder.</p>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-line" x-show="step > 1" @click="go(step - 1)">Sebelumnya</button>
                <span x-show="step === 1"></span>
                <button type="button" class="btn btn-ink" x-show="step < 3" @click="go(step + 1)">Lanjut</button>
                <button type="submit" class="btn btn-accent" x-show="step === 3" x-cloak>Kirim untuk Verifikasi</button>
            </div>
        </form>

        <aside class="preview" aria-label="Pratinjau lowongan">
            <span class="lbl">Pratinjau di daftar lowongan</span>
            <article class="job" :class="pkg === 'tenaga_ahli' && 'hot'">
                <div class="strip" x-show="pkg === 'tenaga_ahli'" aria-hidden="true"></div>
                <div class="body">
                    <div style="display:flex;justify-content:space-between;gap:10px">
                        <span class="badge b-avail" x-show="pkg === 'tenaga_ahli'">Tenaga Ahli</span>
                        <span class="lbl" x-show="pkg !== 'tenaga_ahli'" x-text="pkgs[pkg].label"></span>
                        <span class="lbl" x-text="'Tayang ' + pkgs[pkg].days + ' hari'"></span>
                    </div>
                    <h3 x-text="f.title || 'Judul posisi Anda'"></h3>
                    <p class="co">{{ $company->name }} · <span x-text="f.location"></span></p>
                    <dl>
                        <dt class="lbl">Jenjang min.</dt><dd x-text="f.jenjang || '-'"></dd>
                        <dt class="lbl">Pengalaman</dt><dd x-text="f.exp ? f.exp + ' thn' : 'Fresh grad'"></dd>
                        <dt class="lbl">Durasi</dt><dd x-text="(f.duration || '-') + ' bln'"></dd>
                    </dl>
                </div>
            </article>
            <div class="sum"><span>Total</span><b class="mono" x-text="rupiah(pkgs[pkg].price)"></b></div>
        </aside>
    </div>
</div>
</x-layouts.app>
