@props(['talent'])
@php($cert = $talent->primaryCertification)
<article @class(['tcard', 'intern' => ! $talent->isAlumni()])>
    <div class="top">
        <div class="av" aria-hidden="true">@if ($talent->photoUrl())<img src="{{ $talent->photoUrl() }}" alt="" loading="lazy">@else{{ $talent->initials() }}@endif</div>
        <div>
            <h3><a href="{{ route('talents.show', $talent) }}" style="text-decoration:none">{{ $talent->name }}</a>
                @if ($talent->isSkkVerified())<i class="ph ph-seal-check verified-mark" role="img" aria-label="SKK terverifikasi" title="SKK terverifikasi admin Kabelota"></i>@endif</h3>
            <p class="role">{{ $talent->headline }}</p>
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
        <a class="textlink" href="{{ route('talents.show', $talent) }}" style="font-size:13px">Lihat profil</a>
    </div>
</article>
