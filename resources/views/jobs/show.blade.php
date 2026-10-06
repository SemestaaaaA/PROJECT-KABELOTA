@php
    $c = $job->company;
    $me = auth()->user();
    $applied = $me?->isTalent() && in_array($job->id, $me->appliedJobIds());
@endphp
<x-layouts.app :title="$job->title.' · Lowongan Kabelota'" :description="$c->name.' membuka lowongan '.$job->title.' di '.$job->location.'.'">
@php
    // Google for Jobs structured data (schema.org/JobPosting).
    $jobLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $job->title,
        'description' => nl2br(e($job->description)),
        'datePosted' => ($job->approved_at ?? $job->created_at)->toDateString(),
        'validThrough' => $job->closes_at->endOfDay()->toIso8601String(),
        'employmentType' => $job->package === 'magang' ? 'INTERN' : 'CONTRACTOR',
        'hiringOrganization' => array_filter([
            '@type' => 'Organization',
            'name' => $c->name,
            'sameAs' => $c->website,
            'logo' => $c->logoUrl() ? url($c->logoUrl()) : null,
        ]),
        'jobLocation' => [
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $job->location,
                'addressRegion' => 'Sulawesi Tengah',
                'addressCountry' => 'ID',
            ],
        ],
        'directApply' => true,
    ]);
@endphp
<x-slot:head>
    <script type="application/ld+json">{!! json_encode($jobLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</x-slot:head>
<div class="wrap">
    <p style="padding-top:24px;font-size:14px"><a class="textlink" href="{{ route('jobs.index') }}">&lsaquo; Semua lowongan</a></p>
    <div class="profile">
        <article class="cv">
            @if ($job->isHighlighted())<div class="hazard" style="height:8px" aria-hidden="true"></div>@endif
            <div class="cv-head" style="grid-template-columns:72px 1fr">
                <div class="av co-logo" style="width:72px;height:72px" aria-hidden="true">@if ($c->logoUrl())<img src="{{ $c->logoUrl() }}" alt="">@else<i class="ph ph-buildings"></i>@endif</div>
                <div>
                    <span @class(['badge', 'b-avail' => $job->isHighlighted(), 'b-plain' => ! $job->isHighlighted()])>{{ $job->isHighlighted() ? 'Tenaga Ahli / Tender' : $job->packageLabel() }}</span>
                    <h1 style="margin-top:8px">{{ $job->title }}</h1>
                    <p><a class="textlink" href="{{ route('companies.show', $c) }}">{{ $c->name }}</a> · {{ $job->location }}</p>
                </div>
            </div>
            <div class="cv-block">
                <div><span class="lbl">Jenjang SKK min.</span><span class="v">{{ $job->min_jenjang ? $job->min_jenjang.' · '.config('kabelota.jenjang')[$job->min_jenjang] : 'Tidak disyaratkan' }}</span></div>
                <div><span class="lbl">Pengalaman</span><span class="v">{{ $job->min_experience ? $job->min_experience.' tahun' : 'Fresh graduate' }}</span></div>
                <div><span class="lbl">Durasi</span><span class="v">{{ $job->duration_months }} bulan</span></div>
                <div><span class="lbl">Konsentrasi</span><span class="v" style="font-family:var(--f-body)">{{ $job->concentration ?? 'Semua' }}</span></div>
            </div>
            <section class="cv-sec">
                <h2>Deskripsi pekerjaan</h2>
                <p style="white-space:pre-line">{{ $job->description }}</p>
            </section>
            @if ($c->about)
            <section class="cv-sec">
                <h2>Tentang {{ $c->name }}</h2>
                <p>{{ $c->about }}</p>
            </section>
            @endif
        </article>

        <aside class="side">
            <div class="card">
                <div style="display:flex;justify-content:space-between;gap:8px"><span class="lbl">{{ $closed ? 'Sudah ditutup' : 'Tutup' }}</span><span class="mono">{{ $job->closes_at->translatedFormat('j M Y') }}</span></div>
                <div style="display:flex;justify-content:space-between;gap:8px"><span class="lbl">Pelamar</span><span class="mono">{{ $job->applications_count }}</span></div>
                @if ($closed)
                    <button class="btn btn-accent" disabled>Lowongan Ditutup</button>
                @elseif ($applied)
                    <a class="btn btn-line" href="{{ route('talent.applications') }}"><i class="ph ph-check" aria-hidden="true"></i> Sudah Melamar</a>
                @elseif ($me?->isTalent())
                    <button type="button" class="btn btn-accent" @click="$store.apply.show({ id: {{ $job->id }}, title: @js($job->title), company: @js($c->name), url: @js(route('jobs.apply', $job)) })">Lamar Lowongan</button>
                @elseif ($me?->isCompany())
                    <p class="demo-note">Melamar hanya untuk akun talenta.</p>
                @else
                    <button type="button" class="btn btn-accent" @click="$store.auth.show({ tab: 'masuk', reason: 'Masuk sebagai talenta untuk melamar lowongan ini. Gratis.' })">Lamar Lowongan</button>
                @endif
            </div>
            <a class="card company-card" href="{{ route('companies.show', $c) }}">
                <span class="lbl">Perusahaan</span>
                <b>{{ $c->name }}</b>
                <span class="demo-note">{{ ucfirst(str_replace('_', ' ', $c->type)) }} · {{ $c->city }}</span>
                <span class="badge b-ok" style="justify-self:start"><i class="ph ph-seal-check" aria-hidden="true"></i> Terverifikasi</span>
            </a>
        </aside>
    </div>
</div>
</x-layouts.app>
