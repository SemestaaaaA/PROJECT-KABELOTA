@php
    $cert = $talent->certifications->first();
    $canOffer = $talent->availability !== \App\Enums\Availability::TidakTersedia;
    $isCompany = session('demo_role') === 'perusahaan';
    $openModal = $errors->offer->any();
@endphp
<x-layouts.app :title="$talent->name.' · Kabelota'">
<div class="wrap" x-data="{ open: @js($openModal) }" @keydown.escape.window="open = false">
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
                <div class="av" aria-hidden="true">{{ $talent->initials() }}</div>
                <div>
                    <h1>{{ $talent->name }}</h1>
                    <p>{{ $talent->headline }} · {{ $talent->isAlumni() ? 'Alumni' : 'Mahasiswa' }} Teknik Sipil UNTAD</p>
                    @unless ($talent->isAlumni())<span class="badge b-intern" style="margin-top:8px"><i class="ph ph-student" aria-hidden="true"></i> Intern for Hire</span>@endunless
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
            @if (session('my_talent_id') === $talent->id)
                <div class="card"><b>Ini profil Anda</b><span class="demo-note">Beginilah perusahaan melihat Anda.</span><a class="btn btn-ink" href="{{ route('profile.edit') }}">Ubah Profil</a></div>
            @endif
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <span class="lbl">Ketersediaan</span>
                    <x-avail-badge :status="$talent->availability" />
                </div>
                <div><span class="lbl">Bersedia ditempatkan di</span><p style="margin-top:4px">{{ implode(', ', $talent->preferred_locations ?? [$talent->city]) }}</p></div>
                @if ($canOffer)
                    @if ($isCompany)
                        <button type="button" class="btn btn-accent" @click="open = true">Ajukan Rekrut</button>
                    @else
                        <button type="button" class="btn btn-accent" @click="$store.auth.show({ tab: 'masuk', role: 'perusahaan', reason: 'Masuk sebagai perusahaan terverifikasi untuk mengajukan rekrut.' })">Ajukan Rekrut</button>
                    @endif
                @else
                    <button type="button" class="btn btn-accent" disabled>Ajukan Rekrut</button>
                    <p style="font-size:13.5px;color:var(--muted)">Talenta ini sedang tidak menerima tawaran.</p>
                @endif
            </div>
            <div class="locked">
                <b>Kontak dan dokumen terkunci</b>
                Nomor HP, email, CV, dan scan SKK terbuka untuk perusahaan terverifikasi setelah talenta menerima tawaran.
            </div>
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
                        <input class="box @error('company_name', 'offer') is-err @enderror" id="company_name" name="company_name" value="{{ old('company_name', 'CV Lembah Palu Konsultan') }}">
                        @error('company_name', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="contact_name">Nama HRD</label>
                        <input class="box @error('contact_name', 'offer') is-err @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name') }}">
                        @error('contact_name', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="fld"><label for="contact_email">Email HRD</label>
                    <input class="box @error('contact_email', 'offer') is-err @enderror" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}">
                    @error('contact_email', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="position">Posisi yang ditawarkan</label>
                    <input class="box @error('position', 'offer') is-err @enderror" id="position" name="position" value="{{ old('position') }}" placeholder="mis. Site Engineer, Paket Preservasi Jalan">
                    @error('position', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="two">
                    <div class="fld"><label for="start_date">Mulai</label>
                        <input class="box @error('start_date', 'offer') is-err @enderror" id="start_date" name="start_date" type="date" value="{{ old('start_date') }}">
                        @error('start_date', 'offer')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="duration">Durasi</label>
                        <input class="box" id="duration" name="duration" value="{{ old('duration') }}" placeholder="mis. 8 bulan"></div>
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
</div>
</x-layouts.app>
