@props(['talent'])
@php($cert = $talent->primaryCertification)
<article @class(['tcard', 'intern' => ! $talent->isAlumni()])>
    <div class="top">
        <div class="av" aria-hidden="true">@if ($talent->photoUrl())<img src="{{ $talent->photoUrl() }}" alt="" loading="lazy">@else{{ $talent->initials() }}@endif</div>
        <div>
            <h3><a class="card-link" href="{{ route('talents.show', $talent) }}">{{ $talent->name }}</a>
                @if ($talent->isSkkVerified())<i class="ph ph-seal-check verified-mark" role="img" aria-label="SKK terverifikasi" title="SKK terverifikasi admin Kabelota"></i>@endif</h3>
            <p class="role">{{ $talent->headline }}</p>
            @php($skills = array_slice($talent->skills ?? [], 0, 2))
            @if ($skills || ($cert && ! $cert->isValid()))
                <div class="tags">
                    @if ($cert && ! $cert->isValid())<span class="tag tag-warn"><i class="ph ph-warning" aria-hidden="true"></i> SKK kedaluwarsa</span>@endif
                    @foreach ($skills as $skill)<span class="tag">{{ $skill }}</span>@endforeach
                    @if (count($talent->skills ?? []) > 2)<span class="tag tag-more">+{{ count($talent->skills) - 2 }}</span>@endif
                </div>
            @endif
        </div>
    </div>
    <div class="tblock">
        @if ($talent->isAlumni())
            <div><span class="lbl">Jenjang</span><span class="v">{{ $cert ? $cert->jenjang.' '.str_replace(['Ahli ', 'Teknisi/Analis'], ['', 'Tek.'], $cert->jenjangLabel()) : '-' }}</span></div>
            <div><span class="lbl">Pengalaman</span><span class="v">{{ $talent->experienceYears() }} thn</span></div>
        @else
            <div><span class="lbl">Status</span><span class="v">Mhs</span></div>
            <div><span class="lbl">Semester</span><span class="v">{{ $talent->semester }}</span></div>
        @endif
        <div><span class="lbl">Lokasi</span><span class="v" style="font-family:var(--f-body);font-weight:600">{{ $talent->city }}</span></div>
    </div>
    <div class="st">
        @if ($talent->isAlumni())
            <x-avail-badge :status="$talent->availability" />
        @else
            <span class="badge b-intern"><i class="ph ph-student" aria-hidden="true"></i> Intern for Hire</span>
        @endif
        <span class="card-cta" aria-hidden="true">Lihat profil <i class="ph ph-arrow-right"></i></span>
    </div>
</article>
