@php
    $cert = $talent->certifications->first();
    $canOffer = $talent->availability !== \App\Enums\Availability::TidakTersedia;
    $viewer = auth()->user();
    $isCompany = (bool) $viewer?->isVerifiedCompany();
    $isPendingCompany = $viewer?->isCompany() && ! $isCompany;
    $isTalentViewer = (bool) $viewer?->isTalent();
    $isOwner = $viewer && $talent->user_id === $viewer->id;
    $docs = collect(['cv' => ['CV', $talent->cv_path], 'skk' => ['Scan SKK', $talent->skk_scan_path], 'transkrip' => ['Transkrip nilai', $talent->transcript_path]])
        ->filter(fn ($d, $k) => $talent->isAlumni() ? $k !== 'transkrip' : $k !== 'skk');
    $completeness = $isOwner ? $talent->completeness() : null;
    $openModal = $errors->offer->any();
@endphp
<x-layouts.app :title="$talent->name.' · Kabelota'">
<div class="wrap" x-data="{ open: @js($openModal), statusOpen: @js($errors->status->any()), pdf: null }" @keydown.escape.window="open = false; statusOpen = false; pdf = null">
    <p style="padding-top:24px;font-size:14px"><a class="textlink" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('talents.index') }}">&lsaquo; Kembali ke hasil pencarian</a></p>

    @if (session('offer_sent'))
        <div class="ok-banner" role="status">
            <b>Tawaran terkirim.</b> {{ $talent->name }} menerima tawaran untuk posisi "{{ session('offer_sent') }}". Kontaknya terbuka setelah ia memilih Terima.
            <span style="color:var(--muted)">(Mode demo: email belum benar-benar dikirim.)</span>
        </div>
    @endif

    <div class="profile">
        <article class="cv">
            <div class="cv-head">
                <div class="av" aria-hidden="true">@if ($talent->photoUrl())<img src="{{ $talent->photoUrl() }}" alt="">@else{{ $talent->initials() }}@endif</div>
                <div>
                    <h1>{{ $talent->name }}</h1>
                    <p>{{ $talent->headline }} · {{ $talent->isAlumni() ? 'Alumni' : 'Mahasiswa' }} Teknik Sipil UNTAD</p>
                    <div class="chips" style="margin-top:8px">
                        @unless ($talent->isAlumni())<span class="badge b-intern"><i class="ph ph-student" aria-hidden="true"></i> Intern for Hire</span>@endunless
                        <span @class(['badge', 'b-hmts' => $talent->isHmtsActive(), 'b-off' => ! $talent->isHmtsActive()]) title="Keanggotaan HMTS Universitas Tadulako">HMTS · {{ $talent->hmtsLabel() }}@if ($talent->hmts_position) · {{ $talent->hmts_position }}@endif</span>
                    </div>
                </div>
            </div>
            <div class="cv-block">
                @if ($talent->isAlumni())
                    <div><span class="lbl">Jenjang tertinggi</span><span class="v">{{ $cert ? $cert->jenjang.' · '.$cert->jenjangLabel() : 'Belum ada SKK' }}</span></div>
                    <div><span class="lbl">Pengalaman</span><span class="v">{{ $talent->experienceYears() }} tahun</span></div>
                    <div><span class="lbl">Lulus</span><span class="v">{{ $talent->graduation_year }}</span></div>
                @else
                    <div><span class="lbl">Semester</span><span class="v">{{ $talent->semester }}</span></div>
                    <div><span class="lbl">Topik TA</span><span class="v" style="font-family:var(--f-body);font-size:14px">{{ $talent->thesis_topic }}</span></div>
                    <div><span class="lbl">Status</span><span class="v">Cari magang</span></div>
                @endif
                <div><span class="lbl">IPK</span><span class="v">{{ $talent->gpa ?? '-' }}</span></div>
            </div>

            <section class="cv-sec">
                <h2>Akademik</h2>
                <p>Konsentrasi <b>{{ $talent->concentration }}</b>, Jurusan Teknik Sipil, Universitas Tadulako.</p>
            </section>

            @if ($talent->bio)
            <section class="cv-sec"><h2>Tentang</h2><p>{{ $talent->bio }}</p></section>
            @endif

            @if ($talent->isAlumni())
            <section class="cv-sec">
                <h2>Sertifikat SKK Konstruksi</h2>
                @forelse ($talent->certifications as $c)
                    <div class="skk">
                        <x-jenjang :level="$c->jenjang" />
                        <div><b>{{ $c->jabatan_kerja }}</b><div class="mono" style="font-size:13px;color:var(--muted)">No. Reg {{ $c->registration_number }}</div></div>
                        <span @class(['badge', 'b-ok' => $c->isValid(), 'b-off' => ! $c->isValid()])>{{ $c->isValid() ? 'Berlaku s.d. '.$c->expires_at->format('m/Y') : 'Kedaluwarsa' }}</span>
                    </div>
                @empty
                    <p style="color:var(--muted)">Belum mengunggah SKK.</p>
                @endforelse
            </section>
            @endif

            @if (! empty($talent->skills))
            <section class="cv-sec">
                <h2>Keahlian software dan alat</h2>
                <div class="chips">@foreach ($talent->skills as $skill)<span class="chip">{{ $skill }}</span>@endforeach</div>
            </section>
            @endif

            <section class="cv-sec">
                <h2>Dokumen</h2>
                <div class="doclinks">
                    @foreach ($docs as $type => [$label, $path])
                        @if (! $path)
                            <span class="doclink off"><i class="ph ph-file-dashed" aria-hidden="true"></i> {{ $label }} <small>belum diunggah</small></span>
                        @elseif ($isCompany || $isOwner)
                            <button type="button" class="doclink" @click="pdf = { title: @js($label), url: @js(route('talents.document', [$talent, $type])) }"><i class="ph ph-file-pdf" aria-hidden="true"></i> {{ $label }} <small>lihat</small></button>
                        @else
                            <span class="doclink"><i class="ph ph-lock-simple" aria-hidden="true"></i> {{ $label }} <small>untuk perusahaan terverifikasi</small></span>
                        @endif
                    @endforeach
                </div>
            </section>

            <section class="cv-sec">
                <h2>{{ $talent->isAlumni() ? 'Riwayat proyek' : 'Organisasi dan pengalaman' }}</h2>
                @foreach ($talent->projects as $p)
                    <div class="proj">
                        <span class="yr">{{ $p->period() }}</span>
                        <div>
                            <b>{{ $p->name }}</b>
                            <span class="meta">{{ $p->position }} · {{ $p->location }}@if ($p->contractor !== '-') · {{ $p->contractor }}@endif</span>
                            @if ($p->client)<div class="meta">Pemberi kerja: {{ $p->client }}</div>@endif
                            @if ($p->description)<p>{{ $p->description }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </section>
        </article>

        <aside class="side">
            @if ($isOwner)
                <div class="card owner">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                        <b>Ini profil Anda</b>
                        <span @class(['badge', 'b-intern' => ! $talent->isAlumni(), 'b-plain' => $talent->isAlumni()])>{{ $talent->isAlumni() ? 'Alumni' : 'Mahasiswa' }}</span>
                    </div>
                    <div class="meter" style="padding:0;border:0">
                        <div class="meter-h"><span>Kelengkapan</span><span class="mono">{{ $completeness['percent'] }}%</span></div>
                        <div class="bar"><span style="width:{{ $completeness['percent'] }}%"></span></div>
                        <ul>
                            @foreach ($completeness['items'] as $label => $done)
                                @unless ($done)<li><i class="ph ph-circle" aria-hidden="true"></i> {{ $label }}</li>@endunless
                            @endforeach
                        </ul>
                    </div>
                    <a class="btn btn-ink" href="{{ route('profile.edit') }}"><i class="ph ph-pencil-simple" aria-hidden="true"></i> Ubah Profil</a>
                    <button type="button" class="btn btn-line" @click="statusOpen = true"><i class="ph ph-arrows-left-right" aria-hidden="true"></i> {{ $talent->isAlumni() ? 'Ubah ke Mahasiswa' : 'Saya Sudah Lulus' }}</button>
                </div>
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                        <span class="lbl">Ketersediaan</span>
                        <x-avail-badge :status="$talent->availability" />
                    </div>
                    <div><span class="lbl">Bersedia ditempatkan di</span><p style="margin-top:4px">{{ implode(', ', $talent->preferred_locations ?? [$talent->city]) }}</p></div>
                </div>
            @else
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                        <span class="lbl">Ketersediaan</span>
                        <x-avail-badge :status="$talent->availability" />
                    </div>
                    <div><span class="lbl">Bersedia ditempatkan di</span><p style="margin-top:4px">{{ implode(', ', $talent->preferred_locations ?? [$talent->city]) }}</p></div>
                    @if ($isTalentViewer)
                        <p class="demo-note">Ajukan Rekrut hanya tersedia untuk akun perusahaan.</p>
                    @elseif (! $canOffer)
                        <button type="button" class="btn btn-accent" disabled>Ajukan Rekrut</button>
                        <p class="demo-note">Talenta ini sedang tidak menerima tawaran.</p>
                    @elseif ($isCompany)
                        <button type="button" class="btn btn-accent" @click="open = true">Ajukan Rekrut</button>
                    @elseif ($isPendingCompany)
                        <button type="button" class="btn btn-accent" disabled>Ajukan Rekrut</button>
                        <p class="demo-note">Terbuka setelah perusahaan Anda diverifikasi admin. <a class="textlink" href="{{ route('company.profile') }}">Cek status</a></p>
                    @else
                        <button type="button" class="btn btn-accent" @click="$store.auth.show({ tab: 'masuk', role: 'perusahaan', reason: 'Masuk sebagai perusahaan terverifikasi untuk mengajukan rekrut.' })">Ajukan Rekrut</button>
                    @endif
                </div>
                <div class="locked">
                    <b><i class="ph ph-lock-simple" aria-hidden="true"></i> Kontak terkunci</b>
                    Nomor HP dan email terbuka untuk perusahaan setelah talenta menerima tawarannya.
                </div>
            @endif
        </aside>
    </div>

    @if ($canOffer && $isCompany)
    <div class="modal-bg" x-show="open" x-cloak x-transition.opacity @click.self="open = false">
        <form class="sheet" method="post" action="{{ route('offers.store', $talent) }}" role="dialog" aria-modal="true" aria-labelledby="offer-h" x-trap.noscroll="open">
            @csrf
            <header>
                <span class="lbl">Ajukan rekrut ke</span>
                <h2 id="offer-h">{{ $talent->name }}, {{ $talent->headline }}</h2>
            </header>
            <div class="content">
                <div class="two">
                    <div class="fld"><label for="company_name">Nama perusahaan</label>
                        <input class="box @error('company_name', 'offer') is-err @enderror" id="company_name" name="company_name" value="{{ old('company_name', $viewer?->company?->name) }}">
                        @error('company_name', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="contact_name">Nama HRD</label>
                        <input class="box @error('contact_name', 'offer') is-err @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name', $viewer?->company?->contact_name) }}">
                        @error('contact_name', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="fld"><label for="contact_email">Email HRD</label>
                    <input class="box @error('contact_email', 'offer') is-err @enderror" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $viewer?->email) }}">
                    @error('contact_email', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="position">Posisi yang ditawarkan</label>
                    <input class="box @error('position', 'offer') is-err @enderror" id="position" name="position" value="{{ old('position') }}" placeholder="mis. Site Engineer, Paket Preservasi Jalan">
                    @error('position', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="two">
                    <div class="fld"><label for="start_date">Mulai</label>
                        <input class="box @error('start_date', 'offer') is-err @enderror" id="start_date" name="start_date" type="date" value="{{ old('start_date') }}">
                        @error('start_date', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="duration">Durasi kontrak</label>
                        <div class="box-suffix"><input class="box mono @error('duration', 'offer') is-err @enderror" id="duration" name="duration" type="number" inputmode="numeric" min="1" max="60" step="1" value="{{ old('duration') }}" placeholder="mis. 24"><span aria-hidden="true">bulan</span></div>
                        <span class="demo-note">Dalam bulan. 1 tahun = 12 bulan.</span>
                        @error('duration', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="fld"><label for="message">Pesan untuk talenta</label>
                    <textarea class="box @error('message', 'offer') is-err @enderror" id="message" name="message" rows="4" placeholder="Jelaskan proyek, lokasi, dan fasilitas.">{{ old('message') }}</textarea>
                    @error('message', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                <p class="privacy">{{ \Illuminate\Support\Str::before($talent->name, ',') }} menerima email dan memilih Terima atau Tolak. Nomor HP dan email baru terlihat setelah ia menerima.</p>
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="open = false">Batal</button>
                <button type="submit" class="btn btn-accent">Kirim Tawaran</button>
            </footer>
        </form>
    </div>
    @endif
    @if ($isOwner)
    <div class="modal-bg" x-show="statusOpen" x-cloak x-transition.opacity @click.self="statusOpen = false">
        <form class="sheet" method="post" action="{{ route('profile.status') }}" role="dialog" aria-modal="true" aria-labelledby="status-h" x-trap.noscroll="statusOpen">
            @csrf
            <input type="hidden" name="type" value="{{ $talent->isAlumni() ? 'mahasiswa' : 'alumni' }}">
            <header>
                <span class="lbl">Ubah status</span>
                <h2 id="status-h">{{ $talent->isAlumni() ? 'Kembali berstatus mahasiswa' : 'Saya sudah lulus' }}</h2>
                <p class="demo-note">{{ $talent->isAlumni() ? 'Misalnya sedang melanjutkan studi. Profil akan tampil dengan tanda Intern for Hire.' : 'Profil Anda pindah ke daftar tenaga ahli. SKK bisa ditambahkan setelahnya.' }}</p>
            </header>
            <div class="content">
                @if ($talent->isAlumni())
                    <div class="fld"><label for="st-sem">Semester sekarang</label>
                        <input class="box @error('semester', 'status') is-err @enderror" id="st-sem" name="semester" inputmode="numeric" value="{{ old('semester') }}">
                        @error('semester', 'status')<span class="err">{{ $message }}</span>@enderror</div>
                @else
                    <div class="two">
                        <div class="fld"><label for="st-grad">Tahun lulus</label>
                            <input class="box @error('graduation_year', 'status') is-err @enderror" id="st-grad" name="graduation_year" inputmode="numeric" value="{{ old('graduation_year', now()->year) }}">
                            @error('graduation_year', 'status')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="fld"><label for="st-since">Mulai bekerja di konstruksi</label>
                            <input class="box @error('experience_since', 'status') is-err @enderror" id="st-since" name="experience_since" inputmode="numeric" value="{{ old('experience_since', now()->year) }}">
                            @error('experience_since', 'status')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                @endif
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="statusOpen = false">Batal</button>
                <button type="submit" class="btn btn-accent">Simpan Status</button>
            </footer>
        </form>
    </div>
    @endif
    <div class="modal-bg" x-show="pdf" x-cloak x-transition.opacity @click.self="pdf = null">
        <div class="sheet pdf-sheet" role="dialog" aria-modal="true" aria-labelledby="pdf-h" x-trap.noscroll="pdf">
            <header style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                <h2 id="pdf-h" x-text="pdf?.title"></h2>
                <div style="display:flex;gap:8px">
                    <a class="btn btn-line btn-sm" :href="pdf ? pdf.url + '?unduh=1' : '#'"><i class="ph ph-download-simple" aria-hidden="true"></i> Unduh</a>
                    <button type="button" class="btn btn-ink btn-sm" @click="pdf = null">Tutup</button>
                </div>
            </header>
            <template x-if="pdf"><iframe :src="pdf.url + '#toolbar=0'" :title="pdf.title" class="pdf-frame"></iframe></template>
        </div>
    </div>
</div>
</x-layouts.app>
