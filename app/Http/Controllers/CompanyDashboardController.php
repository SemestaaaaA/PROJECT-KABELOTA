<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Company side: sent offers (contact revealed after acceptance), own jobs, applicants. */
class CompanyDashboardController extends Controller
{
    public function offers(Request $request): View
    {
        return view('company.offers', [
            'offers' => $request->user()->company->offers()->with('talent')->latest()->get(),
        ]);
    }

    public function jobs(Request $request): View
    {
        return view('company.jobs', [
            'jobs' => $request->user()->company->jobPostings()->withCount([
                'applications',
                'applications as new_applications_count' => fn ($q) => $q->where('status', 'baru'),
            ])->latest()->get(),
        ]);
    }

    public function applicants(Request $request, JobPosting $job): View
    {
        abort_unless($job->company_id === $request->user()->company->id, 403);

        return view('company.applicants', [
            'job' => $job,
            'applications' => $job->applications()->with(['talent.primaryCertification'])
                ->orderByRaw("case status when 'baru' then 0 when 'ditinjau' then 1 when 'diterima' then 2 else 3 end")
                ->latest()->get(),
        ]);
    }

    public function updateApplication(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->jobPosting->company_id === $request->user()->company->id, 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(JobApplication::STATUSES))]]);
        $application->update(['status' => $data['status'], 'status_changed_at' => now()]);

        return back()->with('status', 'Status lamaran '.\Illuminate\Support\Str::before($application->talent->name, ',').' diubah ke '.$application->statusLabel().'.');
    }
}
