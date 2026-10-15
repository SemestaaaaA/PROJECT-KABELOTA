<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Services\HiringDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Company side: sent offers (contact revealed once the talent is interested or takes a job offer), own jobs, applicants. */
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

    public function closeJob(Request $request, JobPosting $job): RedirectResponse
    {
        abort_unless($job->company_id === $request->user()->company?->id, 403);
        abort_unless($job->status === 'aktif', 422);

        $job->update(['status' => 'ditutup']);

        return back()->with('status', "Lowongan \"{$job->title}\" ditutup. Pelamar yang sudah masuk tetap bisa Anda tinjau.");
    }

    public function applicants(Request $request, JobPosting $job): View
    {
        abort_unless($job->company_id === $request->user()->company?->id, 403);

        return view('company.applicants', [
            'job' => $job,
            'applications' => $job->applications()->with(['talent.primaryCertification'])
                ->orderByRaw("case status when 'tawaran_kerja' then 0 when 'baru' then 1 when 'ditinjau' then 2 when 'diambil' then 3 else 4 end")
                ->latest()->get(),
        ]);
    }

    public function updateApplication(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->jobPosting->company_id === $request->user()->company?->id, 403);
        abort_unless($application->companyCanChangeStatus(), 422, 'Status lamaran ini sudah final.');

        $data = $request->validate(['status' => ['required', Rule::in(JobApplication::COMPANY_STATUSES)]]);
        $changed = $application->status !== $data['status'];
        $application->update(['status' => $data['status'], 'status_changed_at' => now()]);
        if ($changed && $data['status'] !== 'baru') {
            $application->talent->user?->notify(new \App\Notifications\ApplicationStatusChanged($application));
        }

        return back()->with('status', 'Status lamaran '.Str::before($application->talent->name, ',').' diubah ke '.$application->statusLabel().'.');
    }

    public function offerJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = HiringDecision::find($type, $id);
        abort_unless($record->companyId() === $request->user()->company?->id, 403);

        $data = $request->validate([
            'job_offer_position' => ['required', 'string', 'max:120'],
            'job_offer_start_date' => ['required', 'date', 'after_or_equal:today'],
            'job_offer_note' => ['nullable', 'string', 'max:500'],
        ], [], ['job_offer_position' => 'posisi', 'job_offer_start_date' => 'tanggal mulai', 'job_offer_note' => 'catatan']);

        $hiring->offerJob($record, $data);

        return back()->with('status', 'Tawaran kerja terkirim ke '.Str::before($record->talent->name, ',').'. Talenta punya '.HiringDecision::OFFER_DAYS.' hari untuk menjawab.');
    }
}
