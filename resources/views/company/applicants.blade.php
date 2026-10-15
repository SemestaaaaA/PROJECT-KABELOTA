<x-layouts.app title="Pelamar · Kabelota">
<div class="wrap" style="max-width:1000px" x-data="{ pdf: null }" @keydown.escape.window="pdf = null">
    <p style="padding-top:24px;font-size:14px"><a class="textlink" href="{{ route('company.jobs') }}">&lsaquo; Lowongan saya</a></p>
    <div class="page-h">
        <h1>{{ $job->title }}</h1>
        <p>{{ $tabs['semua'] }} pelamar. Tinjau dulu, lalu kirim tawaran kerja. Kontak pelamar terbuka setelah tawaran kerja terkirim.</p>
    </div>
    <nav class="page-tabs" aria-label="Saring pelamar">
        @foreach (['semua' => 'Semua', 'baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'tawaran' => 'Tawaran kerja', 'selesai' => 'Selesai'] as $key => $label)
            <a href="{{ route('company.applicants', $key === 'semua' ? $job : [$job, 'status' => $key]) }}" @if ($filter === $key) aria-current="page" @endif>{{ $label }}@if ($tabs[$key])<span class="count">{{ $tabs[$key] }}</span>@endif</a>
        @endforeach
    </nav>

    @forelse ($applications as $app)
        @php($t = $app->talent)
        <article class="inbox-card applicant">
            <div class="inbox-head">
                <div class="av" aria-hidden="true">@if ($t->photoUrl())<img src="{{ $t->photoUrl() }}" alt="">@else{{ $t->initials() }}@endif</div>
                <div>
                    <h2><a href="{{ route('talents.show', $t) }}">{{ $t->name }}</a></h2>
                    <p class="demo-note">{{ $t->headline }}</p>
                    <ul class="applicant-facts">
                        @if ($t->primaryCertification)
                            <li><i class="ph ph-certificate" aria-hidden="true"></i> Jenjang {{ $t->primaryCertification->jenjang }}@if (! $t->primaryCertification->isValid()) <span class="tag tag-warn">SKK kedaluwarsa</span>@endif</li>
                        @endif
                        <li><i class="ph ph-hard-hat" aria-hidden="true"></i> {{ $t->isAlumni() ? ($t->experienceYears() ?? 0).' thn pengalaman' : 'Semester '.$t->semester }}</li>
                        <li><i class="ph ph-map-pin" aria-hidden="true"></i> {{ $t->city }}</li>
                        <li><i class="ph ph-clock" aria-hidden="true"></i> melamar {{ $app->created_at->diffForHumans() }}</li>
                    </ul>
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
                @if ($app->status === 'baru')
                    <form method="post" action="{{ route('company.applications.update', $app) }}">@csrf
                        <input type="hidden" name="status" value="ditinjau">
                        <button class="btn btn-line btn-sm" type="submit"><i class="ph ph-eye" aria-hidden="true"></i> Tandai ditinjau</button>
                    </form>
                @endif
                @if (in_array($app->status, ['baru', 'ditinjau'], true))
                    <x-reject-applicant :application="$app" />
                @endif
                @if ($app->status === 'ditolak' && $app->companyCanChangeStatus())
                    <form method="post" action="{{ route('company.applications.update', $app) }}">@csrf
                        <input type="hidden" name="status" value="ditinjau">
                        <button class="btn btn-line btn-sm" type="submit"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i> Tinjau ulang</button>
                    </form>
                @endif
                @if ($app->canOfferJob())
                    <x-job-offer-form :record="$app" :position="$job->title" />
                @endif
                @if ($app->contactVisible())
                    <div class="contact-reveal">
                        <a href="tel:{{ $t->phone }}" class="mono"><i class="ph ph-phone" aria-hidden="true"></i> {{ $t->phone }}</a>
                        <a href="mailto:{{ $t->email }}" class="mono"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $t->email }}</a>
                    </div>
                @endif
            </div>
            @if ($app->isJobOffer())
                <p class="demo-note">Tawaran kerja <b>{{ $app->job_offer_position }}</b> terkirim. Menunggu jawaban talenta, {{ $app->job_offer_expires_at->isFuture() ? $app->jobOfferDaysLeft().' hari lagi' : 'batas jawab sudah lewat' }}.</p>
            @elseif ($app->status === 'diambil')
                <p class="demo-note">Talenta mengambil tawaran kerja <b>{{ $app->job_offer_position }}</b>.</p>
            @elseif ($app->status === 'ditutup')
                <p class="demo-note">Ditutup otomatis: talenta mengambil tawaran kerja lain.@if ($app->closed_note) Pesan talenta: "{{ $app->closed_note }}"@endif</p>
            @elseif ($app->status === 'kedaluwarsa')
                <p class="demo-note">Tawaran kerja tidak dijawab dalam {{ \App\Services\HiringDecision::OFFER_DAYS }} hari. Anda bisa mengirimnya lagi.</p>
            @elseif ($app->status === 'ditolak' && $app->closed_note)
                <p class="demo-note">Alasan talenta: "{{ $app->closed_note }}"</p>
            @elseif ($app->status === 'ditolak' && $app->company_note)
                <p class="demo-note">Alasan Anda: "{{ $app->company_note }}"</p>
            @endif
        </article>
    @empty
        <div class="empty" style="margin-top:20px">
            @if ($filter !== 'semua')
                <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Tidak ada pelamar di tab ini.</b>
                <a class="textlink" href="{{ route('company.applicants', $job) }}">Lihat semua pelamar</a>
            @else
                <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada pelamar</b>
                Lowongan baru biasanya mendapat pelamar dalam beberapa hari. Sambil menunggu, Anda bisa mencari talenta dan mengajukan rekrut langsung.
            @endif
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
