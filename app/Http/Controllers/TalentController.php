<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TalentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'tipe' => ['nullable', Rule::in(['alumni', 'mahasiswa'])],
            'konsentrasi' => ['nullable', Rule::in(config('kabelota.concentrations'))],
            'jabatan' => ['nullable', Rule::in(array_keys(config('kabelota.jabatan_kerja')))],
            'jenjang' => ['nullable', 'array'],
            'jenjang.*' => [Rule::in(array_keys(config('kabelota.jenjang')))],
            'pengalaman' => ['nullable', 'integer', 'min:0', 'max:40'],
            'lokasi' => ['nullable', Rule::in(config('kabelota.locations'))],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(Availability::class)],
            'view' => ['nullable', Rule::in(['grid', 'list'])],
        ]);

        $talents = Talent::query()
            ->with('primaryCertification')
            ->search($filters)
            ->orderByRaw("case availability when 'tersedia' then 0 when 'terikat_kontrak' then 1 else 2 end")
            ->orderByDesc(
                \App\Models\Certification::select('jenjang')
                    ->whereColumn('talent_id', 'talents.id')
                    ->orderByDesc('jenjang')
                    ->limit(1)
            )
            ->orderBy('experience_since')
            ->paginate(config('kabelota.per_page'))
            ->withQueryString();

        return view('talents.index', [
            'talents' => $talents,
            'filters' => $filters,
            'view' => $filters['view'] ?? 'grid',
        ]);
    }

    public function show(Talent $talent): View
    {
        $talent->load(['certifications', 'projects']);

        return view('talents.show', ['talent' => $talent]);
    }
}
