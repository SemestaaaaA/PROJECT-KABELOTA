@php
    $active = collect([
        'q' => $filters['q'] ?? null,
        'tipe' => isset($filters['tipe']) ? ucfirst($filters['tipe']) : null,
        'konsentrasi' => $filters['konsentrasi'] ?? null,
        'jabatan' => $filters['jabatan'] ?? null,
        'pengalaman' => isset($filters['pengalaman']) ? 'Min. '.$filters['pengalaman'].' thn' : null,
        'lokasi' => $filters['lokasi'] ?? null,
    ])->filter();
    $levels = array_map('intval', $filters['jenjang'] ?? []);
    $statuses = $filters['status'] ?? [];
@endphp
<x-layouts.app title="Cari Talenta · Kabelota">
<div class="wrap">
    <div class="page-h">
        <h1>Cari talenta</h1>
        <p>Saring alumni dan mahasiswa Teknik Sipil UNTAD sesuai syarat tender. Nomor HP dan email tidak ditampilkan.</p>
    </div>

    <div class="explorer">
        @php($activeCount = $active->except('q')->count() + count($levels) + count($statuses))
        <form class="filters" method="get" action="{{ route('talents.index') }}" x-data="{ open: false }" @change="$el.requestSubmit()" aria-label="Filter talenta">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="fld"><label for="q">Nama atau proyek</label>
                <div class="box-icon"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                <input class="box" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="mis. preservasi jalan"></div></div>
            <button type="button" class="btn btn-line filter-toggle" @click="open = !open" :aria-expanded="open" aria-controls="filter-body">
                <i class="ph ph-sliders-horizontal" aria-hidden="true"></i> Filter
                @if ($activeCount)<span class="count">{{ $activeCount }}</span>@endif
                <i class="ph" :class="open ? 'ph-caret-up' : 'ph-caret-down'" aria-hidden="true" style="margin-left:auto"></i>
            </button>
            <div id="filter-body" class="filter-body" :class="open && 'open'">
            <div class="fld"><label for="tipe">Tipe talenta</label>
                <select class="box" id="tipe" name="tipe">
                    <option value="">Alumni dan mahasiswa</option>
                    <option value="alumni" @selected(($filters['tipe'] ?? '') === 'alumni')>Alumni (tenaga ahli)</option>
                    <option value="mahasiswa" @selected(($filters['tipe'] ?? '') === 'mahasiswa')>Mahasiswa (Intern for Hire)</option>
                </select></div>
            <div class="fld"><label for="jabatan">Jabatan kerja SKK</label>
                <select class="box" id="jabatan" name="jabatan">
                    <option value="">Semua jabatan</option>
                    @foreach (array_keys(config('kabelota.jabatan_kerja')) as $j)
                        <option @selected(($filters['jabatan'] ?? '') === $j)>{{ $j }}</option>
                    @endforeach
                </select></div>
            <fieldset class="fld" style="border:0;padding:0;margin:0">
                <legend class="lbl" style="font:600 13px var(--f-body);color:var(--ink);margin-bottom:6px">Jenjang SKK</legend>
                <div class="checks cols">
                    @foreach (config('kabelota.jenjang') as $lvl => $label)
                        <label title="Jenjang {{ $lvl }}, {{ $label }}"><input type="checkbox" name="jenjang[]" value="{{ $lvl }}" @checked(in_array($lvl, $levels))> {{ $lvl }} · {{ str_replace(['Ahli ', 'Teknisi/Analis'], ['', 'Teknisi'], $label) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="fld"><label for="pengalaman">Pengalaman minimal</label>
                <select class="box" id="pengalaman" name="pengalaman">
                    <option value="">Semua</option>
                    @foreach (config('kabelota.experience_options') as $y)<option value="{{ $y }}" @selected(($filters['pengalaman'] ?? null) == $y)>{{ $y >= 5 ? $y.'+ tahun' : $y.' tahun' }}</option>@endforeach
                </select></div>
            <div class="fld"><label for="konsentrasi">Konsentrasi</label>
                <select class="box" id="konsentrasi" name="konsentrasi">
                    <option value="">Semua konsentrasi</option>
                    @foreach (config('kabelota.concentrations') as $k)<option @selected(($filters['konsentrasi'] ?? '') === $k)>{{ $k }}</option>@endforeach
                </select></div>
            <div class="fld"><label for="lokasi">Lokasi kerja</label>
                <select class="box" id="lokasi" name="lokasi">
                    <option value="">Seluruh Sulawesi Tengah</option>
                    <optgroup label="Sulawesi Tengah">
                    @foreach (array_diff(config('kabelota.locations'), [config('kabelota.outside_region')]) as $l)<option @selected(($filters['lokasi'] ?? '') === $l)>{{ $l }}</option>@endforeach
                    </optgroup>
                    <option value="{{ config('kabelota.outside_region') }}" @selected(($filters['lokasi'] ?? '') === config('kabelota.outside_region'))>Luar Sulawesi Tengah</option>
                </select></div>
            <fieldset class="fld" style="border:0;padding:0;margin:0">
                <legend class="lbl" style="font:600 13px var(--f-body);color:var(--ink);margin-bottom:6px">Ketersediaan</legend>
                <div class="checks">
                    @foreach (\App\Enums\Availability::cases() as $a)
                        <label><input type="checkbox" name="status[]" value="{{ $a->value }}" @checked(in_array($a->value, $statuses))> {{ $a->label() }}</label>
                    @endforeach
                </div>
            </fieldset>
            <noscript><button class="btn btn-ink btn-sm" type="submit">Terapkan</button></noscript>
            <a class="textlink" href="{{ route('talents.index', ['view' => $view]) }}" style="font-size:14px">Hapus semua filter</a>
            </div>
        </form>

        <div class="results">
            <div class="toolbar">
                <p style="font-size:15px;color:var(--muted)"><b class="mono" style="color:var(--ink)">{{ $talents->total() }}</b> talenta cocok</p>
                <div class="seg" role="group" aria-label="Tampilan">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'grid', 'page' => null]) }}" aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}" style="text-decoration:none">Grid</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}" aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}" style="text-decoration:none">List</a>
                </div>
            </div>

            @if ($active->isNotEmpty() || $levels || $statuses)
                <div class="active-filters" aria-label="Filter aktif">
                    @foreach ($active as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery([$key => null, 'page' => null]) }}" title="Hapus filter">{{ $label }}</a>
                    @endforeach
                    @foreach ($levels as $lvl)
                        <a href="{{ request()->fullUrlWithQuery(['jenjang' => array_values(array_diff($levels, [$lvl])), 'page' => null]) }}" title="Hapus filter">Jenjang {{ $lvl }}</a>
                    @endforeach
                    @foreach ($statuses as $s)
                        <a href="{{ request()->fullUrlWithQuery(['status' => array_values(array_diff($statuses, [$s])), 'page' => null]) }}" title="Hapus filter">{{ \App\Enums\Availability::from($s)->label() }}</a>
                    @endforeach
                </div>
            @endif

            @if ($talents->isEmpty())
                <div class="empty">
                    <b style="display:block;color:var(--ink);font-size:17px;margin-bottom:6px">Belum ada talenta yang cocok</b>
                    Coba hapus salah satu filter, atau pilih jenjang dan pengalaman yang lebih rendah.
                </div>
            @elseif ($view === 'list')
                <div class="tlist-wrap">
                    <table class="tlist">
                        <thead><tr><th>Nama</th><th>Jabatan kerja</th><th>Jenjang</th><th>Pengalaman</th><th>Lulus</th><th>Lokasi</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($talents as $talent)
                            <tr @class(['intern-row' => ! $talent->isAlumni()])>
                                <td><a href="{{ route('talents.show', $talent) }}"><b>{{ $talent->name }}</b></a><div style="font-size:12.5px;color:var(--muted)">{{ $talent->concentration }}</div>
                                    @unless ($talent->isAlumni())<span class="badge b-intern" style="margin-top:4px"><i class="ph ph-student" aria-hidden="true"></i> Intern for Hire</span>@endunless</td>
                                <td>{{ $talent->primaryCertification?->jabatan_kerja ?? ($talent->isAlumni() ? '-' : 'Mahasiswa smt '.$talent->semester) }}</td>
                                <td>@if ($talent->primaryCertification)<x-jenjang :level="$talent->primaryCertification->jenjang" />@else <span style="color:var(--muted)">-</span>@endif</td>
                                <td class="mono">{{ $talent->isAlumni() ? $talent->experienceYears().' thn' : '-' }}</td>
                                <td class="mono">{{ $talent->graduation_year ?? '-' }}</td>
                                <td>{{ $talent->city }}</td>
                                <td><x-avail-badge :status="$talent->availability" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="tgrid wide">
                    @foreach ($talents as $talent)<x-talent-card :talent="$talent" />@endforeach
                </div>
            @endif

            {{ $talents->links('pagination::kabelota') }}
        </div>
    </div>
</div>
</x-layouts.app>
