<x-layouts.app :title="$company->name.' · Kabelota'" :description="$company->name.', '.$company->city.'. Lowongan aktif di Kabelota.'">
<div class="wrap" style="max-width:1000px">
    <div class="page-h company-hero">
        <div class="co-logo" style="width:84px;height:84px;font-size:34px" aria-hidden="true">@if ($company->logoUrl())<img src="{{ $company->logoUrl() }}" alt="">@else<i class="ph ph-buildings"></i>@endif</div>
        <div>
            <span class="badge b-ok"><i class="ph ph-seal-check" aria-hidden="true"></i> Terverifikasi Kabelota</span>
            <h1 style="margin-top:10px">{{ $company->name }}</h1>
            <p>{{ ucfirst(str_replace('_', ' ', $company->type)) }} · {{ $company->city }}@if ($company->website) · <a class="textlink" href="{{ $company->website }}" target="_blank" rel="noopener nofollow">{{ parse_url($company->website, PHP_URL_HOST) }}</a>@endif</p>
        </div>
    </div>
    @if ($company->about)<p class="prose" style="margin-top:20px">{{ $company->about }}</p>@endif

    <h2 class="h2" style="margin-top:40px;font-size:34px">Lowongan aktif</h2>
    @if ($jobs->isEmpty())
        <div class="empty" style="margin-top:16px">Belum ada lowongan yang sedang tayang.</div>
    @else
        <div class="jobs" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
            @foreach ($jobs as $job)<x-job-card :job="$job" />@endforeach
        </div>
    @endif
</div>
</x-layouts.app>
