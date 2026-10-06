@props(['job'])
<article @class(['job', 'hot' => $job->isHighlighted()])>
    @if ($job->isHighlighted())<div class="strip" aria-hidden="true"></div>@endif
    <div class="body">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
            @if ($job->isHighlighted())
                <span class="badge b-avail">Tenaga Ahli</span>
            @else
                <span class="lbl">{{ $job->packageLabel() }}</span>
            @endif
            <span class="lbl">Tutup {{ $job->closes_at->translatedFormat('j M Y') }}</span>
        </div>
        <h3><a href="{{ route('jobs.show', $job) }}">{{ $job->title }}</a></h3>
        <p class="co job-co">
            <span class="mini-logo" aria-hidden="true">@if ($job->company->logoUrl())<img src="{{ $job->company->logoUrl() }}" alt="">@else{{ mb_substr($job->company->name, 0, 1) }}@endif</span>
            <a href="{{ route('companies.show', $job->company) }}">{{ $job->company->name }}</a> · {{ $job->location }}
        </p>
        <dl>
            @if ($job->min_jenjang)
                <dt class="lbl">Jenjang min.</dt><dd>{{ $job->min_jenjang }} {{ str_replace('Ahli ', '', config('kabelota.jenjang')[$job->min_jenjang]) }}</dd>
            @else
                <dt class="lbl">Konsentrasi</dt><dd>{{ $job->concentration }}</dd>
            @endif
            <dt class="lbl">Pengalaman</dt><dd>{{ $job->min_experience ? $job->min_experience.' thn' : 'Mhs' }}</dd>
            <dt class="lbl">Durasi</dt><dd>{{ $job->duration_months }} bln</dd>
        </dl>
    </div>
    <div class="foot">
        @php($count = $job->applications_count ?? $job->applications()->count())
        <span>{{ $count ? $count.' pelamar' : 'Belum ada pelamar' }}</span>
        @if (auth()->user()?->isTalent())
            @if (in_array($job->id, auth()->user()->appliedJobIds()))
                <a class="btn btn-sm btn-line" href="{{ route('talent.applications') }}"><i class="ph ph-check" aria-hidden="true"></i> Sudah melamar</a>
            @else
                <button type="button" @click="$store.apply.show({ id: {{ $job->id }}, title: @js($job->title), company: @js($job->company->name), url: @js(route('jobs.apply', $job)) })" @class(['btn btn-sm', 'btn-accent' => $job->isHighlighted(), 'btn-line' => ! $job->isHighlighted()])>Lamar</button>
            @endif
        @elseif (auth()->user()?->isCompany())
            <span class="demo-note">Khusus talenta</span>
        @else
            <button type="button" @click="$store.auth.show({ tab: 'masuk', role: 'talenta', reason: 'Masuk sebagai talenta untuk melamar lowongan ini. Gratis.' })" @class(['btn btn-sm', 'btn-accent' => $job->isHighlighted(), 'btn-line' => ! $job->isHighlighted()])>Lamar</button>
        @endif
    </div>
</article>
