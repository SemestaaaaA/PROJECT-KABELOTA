<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Talent;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            // Live counts from the database; see README for alternative stat sets.
            // Cached for 10 minutes: the home page is the busiest page and the counts move slowly.
            'stats' => Cache::remember('home-stats', now()->addMinutes(10), fn () => [
                ['value' => Talent::where('is_visible', true)->count(), 'label' => 'Talenta terdaftar', 'note' => 'Alumni dan mahasiswa Teknik Sipil UNTAD'],
                ['value' => Company::whereNotNull('verified_at')->count(), 'label' => 'Perusahaan terverifikasi', 'note' => 'Konsultan dan kontraktor yang sudah dicek admin'],
                ['value' => JobPosting::open()->count(), 'label' => 'Lowongan aktif', 'note' => 'Dari proyek di Sulawesi Tengah'],
            ]),
            'jobs' => JobPosting::with('company')->withCount('applications')->open()->featuredOrder()->take(3)->get(),
        ]);
    }
}
