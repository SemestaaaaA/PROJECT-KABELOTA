<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** robots.txt and sitemap.xml. Only production is indexable; demo and QA ask crawlers to stay out. */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /akun',
                'Disallow: /profil',
                'Disallow: /perusahaan',
                'Disallow: /tawaran',
                'Disallow: /lamaran',
                'Disallow: /email',
                'Disallow: /reset-sandi',
                'Disallow: /lupa-sandi',
                'Disallow: /talenta/',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap:'.request()->getHost(), now()->addHour(), function () {
            $urls = collect(['home', 'talents.index', 'jobs.index', 'companies', 'about', 'contact', 'privacy', 'terms'])
                ->map(fn ($name) => ['loc' => route($name), 'lastmod' => null]);

            JobPosting::open()->with('company')->get()->each(function (JobPosting $job) use ($urls) {
                $urls->push(['loc' => route('jobs.show', $job), 'lastmod' => $job->updated_at]);
            });

            Company::where('status', 'terverifikasi')->get()->each(function (Company $company) use ($urls) {
                $urls->push(['loc' => route('companies.show', $company), 'lastmod' => $company->updated_at]);
            });

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
