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
        <h3>{{ $job->title }}</h3>
        <p class="co">{{ $job->company->name }} · {{ $job->location }}</p>
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
        <span>{{ $job->applicants_count }} pelamar</span>
        @if (session('demo_role') === 'talenta')
            <button type="button" x-data="{ sent: false }" @click="sent = true" :disabled="sent" x-text="sent ? 'Lamaran terkirim' : 'Lamar'" @class(['btn btn-sm', 'btn-accent' => $job->isHighlighted(), 'btn-line' => ! $job->isHighlighted()])>Lamar</button>
        @else
            <button type="button" @click="$store.auth.show({ tab: 'masuk', role: 'talenta', reason: 'Masuk sebagai talenta untuk melamar lowongan ini. Gratis.' })" @class(['btn btn-sm', 'btn-accent' => $job->isHighlighted(), 'btn-line' => ! $job->isHighlighted()])>Lamar</button>
        @endif
    </div>
</article>
