<x-layouts.app title="Pelamar · Kabelota">
<div class="wrap" style="max-width:1000px" x-data="{ pdf: null }" @keydown.escape.window="pdf = null">
    <p style="padding-top:24px;font-size:14px"><a class="textlink" href="{{ route('company.jobs') }}">&lsaquo; Lowongan saya</a></p>
    <div class="page-h">
        <h1>{{ $job->title }}</h1>
        <p>{{ $applications->count() }} pelamar. Kontak pelamar terbuka saat statusnya Anda ubah ke Diterima.</p>
    </div>

    @forelse ($applications as $app)
        @php($t = $app->talent)
        <article class="inbox-card applicant">
            <div class="inbox-head">
                <div class="av" aria-hidden="true">@if ($t->photoUrl())<img src="{{ $t->photoUrl() }}" alt="">@else{{ $t->initials() }}@endif</div>
                <div>
                    <h2><a href="{{ route('talents.show', $t) }}">{{ $t->name }}</a></h2>
                    <p class="demo-note">{{ $t->headline }} · {{ $t->city }}
                        @if ($t->isAlumni()) · {{ $t->experienceYears() }} thn @if ($t->primaryCertification) · jenjang {{ $t->primaryCertification->jenjang }} @endif
                        @else · semester {{ $t->semester }} @endif</p>
                </div>
                <span class="badge {{ $app->badgeClass() }}">{{ $app->statusLabel() }}</span>
            </div>
            @if ($app->message)<p class="inbox-msg">{{ $app->message }}</p>@endif
            <div class="inbox-actions">
                @if ($t->cv_path)
                    <button type="button" class="btn btn-line btn-sm" @click="pdf = { title: 'CV {{ e(\Illuminate\Support\Str::before($t->name, ',')) }}', url: @js(route('talents.document', [$t, 'cv'])) }"><i class="ph ph-file-pdf" aria-hidden="true"></i> Lihat CV</button>
                @else
                    <span class="demo-note">CV belum diunggah</span>
                @endif
                <form method="post" action="{{ route('company.applications.update', $app) }}" class="status-form">@csrf
                    <label class="sr-only" for="st-{{ $app->id }}">Ubah status</label>
                    <select class="box" id="st-{{ $app->id }}" name="status" onchange="this.form.requestSubmit()">
                        @foreach (\App\Models\JobApplication::STATUSES as $key => $label)<option value="{{ $key }}" @selected($app->status === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <noscript><button class="btn btn-ink btn-sm" type="submit">Simpan</button></noscript>
                </form>
                @if ($app->status === 'diterima')
                    <div class="contact-reveal">
                        <a href="tel:{{ $t->phone }}" class="mono"><i class="ph ph-phone" aria-hidden="true"></i> {{ $t->phone }}</a>
                        <a href="mailto:{{ $t->email }}" class="mono"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $t->email }}</a>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <div class="empty" style="margin-top:20px">
            <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada pelamar</b>
            Lowongan baru biasanya mendapat pelamar dalam beberapa hari. Sambil menunggu, Anda bisa mencari talenta dan mengajukan rekrut langsung.
        </div>
    @endforelse

    <div class="modal-bg" x-show="pdf" x-cloak x-transition.opacity @click.self="pdf = null">
        <div class="sheet pdf-sheet" role="dialog" aria-modal="true" aria-labelledby="pdf-h" x-trap.noscroll="pdf">
            <header style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                <h2 id="pdf-h" x-text="pdf?.title"></h2>
                <div style="display:flex;gap:8px">
                    <a class="btn btn-line btn-sm" :href="pdf ? pdf.url + '?unduh=1' : '#'"><i class="ph ph-download-simple" aria-hidden="true"></i> Unduh</a>
                    <button type="button" class="btn btn-ink btn-sm" @click="pdf = null">Tutup</button>
                </div>
            </header>
            <template x-if="pdf"><iframe :src="pdf.url" :title="pdf.title" class="pdf-frame"></iframe></template>
        </div>
    </div>
</div>
</x-layouts.app>
