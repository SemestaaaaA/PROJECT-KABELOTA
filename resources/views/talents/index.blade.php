@php
    $active = collect([
        'q' => $filters['q'] ?? null,
        'tipe' => isset($filters['tipe']) ? ucfirst($filters['tipe']) : null,
        'konsentrasi' => $filters['konsentrasi'] ?? null,
        'jabatan' => $filters['jabatan'] ?? null,
        'pengalaman' => isset($filters['pengalaman']) ? 'Min. '.$filters['pengalaman'].' thn' : null,
        'lokasi' => $filters['lokasi'] ?? null,
        'terverifikasi' => ! empty($filters['terverifikasi']) ? 'SKK terverifikasi' : null,
    ])->filter();
    $levels = array_map('intval', $filters['jenjang'] ?? []);
    $statuses = $filters['status'] ?? [];
@endphp
<x-layouts.app title="Cari Talenta · Kabelota">
<div class="wrap">
    <div class="page-h page-h-split">
        <div>
            <h1>Cari talenta</h1>
            <p>Saring alumni dan mahasiswa Teknik Sipil UNTAD sesuai syarat tender. Nomor HP dan email tidak ditampilkan.</p>
        </div>
        <dl class="pool">
            <div><dt>Talenta</dt><dd class="mono">{{ $pool['total'] }}</dd></div>
            <div><dt>Tersedia</dt><dd class="mono">{{ $pool['tersedia'] }}</dd></div>
            <div><dt>SKK terverifikasi</dt><dd class="mono">{{ $pool['verified'] }}</dd></div>
        </dl>
    </div>

    <div class="explorer">
        @php
            $activeCount = $active->except('q')->count() + count($levels) + count($statuses);
        @endphp
        <form class="filters" id="talent-filters" method="get" action="{{ route('talents.index') }}" x-data="{ open: false }" @change="$el.requestSubmit()" aria-label="Filter talenta">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="filters-h desk-only-flex"><span class="lbl">Filter</span>@if ($activeCount)<span class="count">{{ $activeCount }}</span><a class="textlink" href="{{ route('talents.index', ['view' => $view]) }}">Reset</a>@endif</div>
            <div class="fld"><label for="q">Nama atau proyek</label>
                <div class="box-icon box-search"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                <input class="box" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="mis. preservasi jalan" enterkeyhint="search">
                <button class="box-go" type="submit" aria-label="Cari"><i class="ph ph-arrow-right" aria-hidden="true"></i></button></div></div>
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
            <label class="check-line"><input type="checkbox" name="terverifikasi" value="1" @checked(! empty($filters['terverifikasi']))> <i class="ph ph-seal-check" aria-hidden="true"></i> Hanya SKK terverifikasi</label>
            <noscript><button class="btn btn-ink btn-sm" type="submit">Terapkan</button></noscript>
            @if ($activeCount)<a class="textlink" href="{{ route('talents.index', ['view' => $view]) }}" style="font-size:14px">Hapus semua filter</a>@endif
            </div>
        </form>

        <div class="results">
            <h2 class="sr-only">Hasil pencarian</h2>
            @php
                $toggle = fn (string $key, $on, $value) => request()->fullUrlWithQuery([$key => $on ? null : $value, 'page' => null]);
                $hasStatus = in_array('tersedia', $statuses);
                $ahli = count(array_intersect([7, 8, 9], $levels)) === 3;
                $quick = [
                    ['Tersedia sekarang', 'ph-lightning', $hasStatus, request()->fullUrlWithQuery(['status' => $hasStatus ? array_values(array_diff($statuses, ['tersedia'])) : [...$statuses, 'tersedia'], 'page' => null])],
                    ['Ahli Muda ke atas', 'ph-medal', $ahli, request()->fullUrlWithQuery(['jenjang' => $ahli ? array_values(array_diff($levels, [7, 8, 9])) : array_values(array_unique([...$levels, 7, 8, 9])), 'page' => null])],
                    ['Intern for Hire', 'ph-student', ($filters['tipe'] ?? null) === 'mahasiswa', $toggle('tipe', ($filters['tipe'] ?? null) === 'mahasiswa', 'mahasiswa')],
                    ['SKK terverifikasi', 'ph-seal-check', ! empty($filters['terverifikasi']), $toggle('terverifikasi', ! empty($filters['terverifikasi']), 1)],
                    ['Domisili Palu', 'ph-map-pin', ($filters['lokasi'] ?? null) === 'Palu', $toggle('lokasi', ($filters['lokasi'] ?? null) === 'Palu', 'Palu')],
                ];
            @endphp
            <div class="quick" role="group" aria-label="Filter cepat">
                @foreach ($quick as [$label, $icon, $on, $url])
                    <a href="{{ $url }}" class="quick-chip" aria-pressed="{{ $on ? 'true' : 'false' }}"><i class="ph {{ $on ? 'ph-check' : $icon }}" aria-hidden="true"></i> {{ $label }}</a>
                @endforeach
            </div>

            <div class="toolbar">
                <p class="result-count" aria-live="polite"><span class="rc-main"><b class="mono">{{ $talents->total() }}</b> talenta cocok</span>
                    @if ($talents->total())<span class="rc-sub">{{ (int) $summary->alumni }} alumni · {{ (int) $summary->mahasiswa }} mahasiswa · {{ (int) $summary->tersedia }} tersedia</span>@endif</p>
                <div class="toolbar-r">
                    <label class="sort"><span class="sr-only">Urutkan</span><i class="ph ph-arrows-down-up" aria-hidden="true"></i>
                        <select class="box" name="urut" form="talent-filters" onchange="this.form.requestSubmit()">
                            @foreach (['relevan' => 'Paling relevan', 'jenjang' => 'Jenjang tertinggi', 'pengalaman' => 'Pengalaman terbanyak', 'terbaru' => 'Baru bergabung'] as $key => $label)
                                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                            @endforeach
                        </select></label>
                    <div class="seg" role="group" aria-label="Tampilan">
                        <a href="{{ request()->fullUrlWithQuery(['view' => 'grid', 'page' => null]) }}" aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}" style="text-decoration:none"><i class="ph ph-squares-four" aria-hidden="true"></i> Grid</a>
                        <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}" aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}" style="text-decoration:none"><i class="ph ph-list-bullets" aria-hidden="true"></i> List</a>
                    </div>
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
                    <a class="clear-all" href="{{ route('talents.index', ['view' => $view]) }}">Hapus semua</a>
                </div>
            @endif

            @if ($talents->isEmpty())
                <div class="empty empty-rich">
                    <i class="ph ph-magnifying-glass-minus" aria-hidden="true"></i>
                    <b>Belum ada talenta yang cocok</b>
                    <p>Filter yang dipilih terlalu ketat. Coba lepaskan jenjang atau pengalaman minimal, atau cari di lokasi lain.</p>
                    <div class="empty-actions">
                        <a class="btn btn-ink btn-sm" href="{{ route('talents.index', ['view' => $view]) }}"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i> Hapus semua filter</a>
                        @if ($levels || isset($filters['pengalaman']))<a class="btn btn-line btn-sm" href="{{ request()->fullUrlWithQuery(['jenjang' => null, 'pengalaman' => null, 'page' => null]) }}">Lepas jenjang dan pengalaman</a>@endif
                    </div>
                </div>
            @elseif ($view === 'list')
                <div class="tlist-wrap" data-skeleton="row:8">
                    <table class="tlist">
                        <thead><tr><th>Nama</th><th>Jabatan kerja</th><th>Jenjang</th><th>Pengalaman</th><th>Lulus</th><th>Lokasi</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($talents as $talent)
                            <tr @class(['intern-row' => ! $talent->isAlumni()]) data-href="{{ route('talents.show', $talent) }}">
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
                <div class="tgrid wide" data-skeleton="talent:9">
                    @foreach ($talents as $talent)<x-talent-card :talent="$talent" />@endforeach
                </div>
            @endif

            {{ $talents->links('pagination::kabelota') }}
        </div>
    </div>
</div>
</x-layouts.app>
