<x-layouts.app title="Lowongan · Kabelota">
<div class="wrap">
    <div class="page-h">
        <h1>Lowongan</h1>
        <p>Lowongan dari perusahaan konstruksi dan konsultan di Sulawesi Tengah. Melamar gratis untuk talenta.</p>
    </div>
    <form method="get" action="{{ route('jobs.index') }}" class="toolbar" style="margin-top:24px;justify-content:flex-start" x-data @change="$el.requestSubmit()">
        <div class="fld" style="min-width:240px;flex:1;max-width:360px"><label for="q">Posisi</label><input class="box" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="mis. site engineer"></div>
        <div class="fld"><label for="lokasi">Lokasi</label>
            <select class="box" id="lokasi" name="lokasi"><option value="">Seluruh Sulawesi Tengah</option>
                @foreach (config('kabelota.locations') as $l)<option @selected(($filters['lokasi'] ?? '') === $l)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="fld"><label for="paket">Jenis</label>
            <select class="box" id="paket" name="paket"><option value="">Semua</option>
                @foreach (config('kabelota.packages') as $key => $pkg)<option value="{{ $key }}" @selected(($filters['paket'] ?? '') === $key)>{{ $pkg['label'] }}</option>@endforeach
            </select></div>
        <button class="btn btn-ink" type="submit" style="align-self:end">Cari</button>
    </form>

    @if ($jobs->isEmpty())
        <div class="empty" style="margin-top:12px"><b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada lowongan yang cocok</b>Coba lokasi lain atau kosongkan kata kunci.</div>
    @else
        <div class="jobs" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
            @foreach ($jobs as $job)<x-job-card :job="$job" />@endforeach
        </div>
    @endif
    {{ $jobs->links('pagination::kabelota') }}
</div>
</x-layouts.app>
