<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'lokasi' => ['nullable', Rule::in(config('kabelota.locations'))],
            'paket' => ['nullable', Rule::in(array_keys(config('kabelota.packages')))],
        ]);

        $jobs = JobPosting::with('company')
            ->open()
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('title', 'like', "%{$t}%"))
            ->when($filters['lokasi'] ?? null, fn ($q, $l) => $q->where('location', $l))
            ->when($filters['paket'] ?? null, fn ($q, $p) => $q->where('package', $p))
            ->featuredOrder()
            ->paginate(12)
            ->withQueryString();

        return view('jobs.index', compact('jobs', 'filters'));
    }
}
