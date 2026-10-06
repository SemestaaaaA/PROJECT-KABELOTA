<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'terverifikasi' => ['nullable', 'boolean'],
            'urut' => ['nullable', Rule::in(['relevan', 'jenjang', 'pengalaman', 'terbaru'])],
        ]);

        $topLevel = \App\Models\Certification::select('jenjang')
            ->whereColumn('talent_id', 'talents.id')
            ->orderByDesc('jenjang')
            ->limit(1);

        $query = Talent::query()->with('primaryCertification')->search($filters);

        // Summary of the whole filtered set, not just this page.
        $summary = (clone $query)->toBase()->reorder()
            ->selectRaw("sum(case when type = 'alumni' then 1 else 0 end) as alumni")
            ->selectRaw("sum(case when type = 'mahasiswa' then 1 else 0 end) as mahasiswa")
            ->selectRaw("sum(case when availability = 'tersedia' then 1 else 0 end) as tersedia")
            ->first();

        $sort = $filters['urut'] ?? 'relevan';
        match ($sort) {
            'jenjang' => $query->orderByDesc($topLevel)->orderBy('experience_since'),
            'pengalaman' => $query->orderByRaw('experience_since is null')->orderBy('experience_since'),
            'terbaru' => $query->latest(),
            // Available first, then highest SKK level, then most experience.
            default => $query->orderByRaw("case availability when 'tersedia' then 0 when 'terikat_kontrak' then 1 else 2 end")
                ->orderByDesc($topLevel)->orderBy('experience_since'),
        };

        return view('talents.index', [
            'talents' => $query->paginate(config('kabelota.per_page'))->withQueryString(),
            'filters' => $filters,
            'view' => $filters['view'] ?? 'grid',
            'sort' => $sort,
            'summary' => $summary,
            // Page header numbers: the whole visible pool, independent of filters.
            'pool' => Cache::remember('talent-pool-stats', now()->addMinutes(10), fn () => [
                'total' => Talent::where('is_visible', true)->count(),
                'tersedia' => Talent::where('is_visible', true)->where('availability', 'tersedia')->count(),
                'verified' => Talent::where('is_visible', true)->whereNotNull('skk_verified_at')->count(),
            ]),
        ]);
    }

    public function show(Request $request, Talent $talent): View
    {
        // Hidden profiles stay reachable for their owner and admins only.
        $user = $request->user();
        abort_unless($talent->is_visible || ($user && ($talent->user_id === $user->id || $user->isAdmin())), 404);

        $talent->load(['certifications', 'projects']);

        return view('talents.show', ['talent' => $talent]);
    }

    /** Private documents: the owner, admins, or a verified company while the profile is visible. */
    public function document(Request $request, Talent $talent, string $type)
    {
        $column = ['cv' => 'cv_path', 'skk' => 'skk_scan_path', 'transkrip' => 'transcript_path'][$type] ?? abort(404);
        $user = $request->user();
        $isOwnerOrAdmin = $talent->user_id === $user->id || $user->isAdmin();
        // Hidden profiles keep their documents private, even from verified companies.
        abort_unless($isOwnerOrAdmin || ($user->isVerifiedCompany() && $talent->is_visible), 403);
        abort_unless($talent->{$column}, 404);

        $filename = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::before($talent->name, ',')).'-'.$type.'.pdf';
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        // Inline by default so the browser previews the PDF; ?unduh=1 forces a download.
        return $request->boolean('unduh')
            ? $disk->download($talent->{$column}, $filename)
            : response()->file($disk->path($talent->{$column}), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'private, no-store',
            ]);
    }
}
