<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Talent side: incoming offers (Terima/Tolak) and own job applications. */
class TalentInboxController extends Controller
{
    private function talent(Request $request): ?Talent
    {
        return Talent::where('user_id', $request->user()->id)->first();
    }

    private function toProfile(): RedirectResponse
    {
        return redirect()->route('profile.edit')->with('status', 'Buat profil dulu untuk menerima tawaran dan melamar.');
    }

    public function offers(Request $request): View|RedirectResponse
    {
        if (! $talent = $this->talent($request)) {
            return $this->toProfile();
        }

        return view('talent.offers', [
            'offers' => $talent->offers()->with('company')->orderByRaw("status = 'menunggu' desc")->latest()->get(),
        ]);
    }

    public function respond(Request $request, RecruitmentOffer $offer): RedirectResponse
    {
        abort_unless($offer->talent?->user_id === $request->user()->id, 403);
        abort_unless($offer->status === 'menunggu', 422, 'Tawaran ini sudah dijawab.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['diterima', 'ditolak'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $offer->update(['status' => $data['decision'], 'response_note' => $data['note'] ?? null, 'responded_at' => now()]);

        return redirect()->route('talent.offers')->with('status', $data['decision'] === 'diterima'
            ? "Tawaran dari {$offer->company_name} diterima. Nomor HP dan email Anda sekarang terlihat oleh perusahaan ini."
            : "Tawaran dari {$offer->company_name} ditolak. Kontak Anda tetap tersembunyi.");
    }

    public function applications(Request $request): View|RedirectResponse
    {
        if (! $talent = $this->talent($request)) {
            return $this->toProfile();
        }

        return view('talent.applications', [
            'applications' => $talent->applications()->with('jobPosting.company')->latest()->get(),
        ]);
    }

    public function apply(Request $request, JobPosting $job): RedirectResponse
    {
        $talent = $this->talent($request);
        if (! $talent) {
            return redirect()->route('profile.edit')->with('status', 'Buat profil dulu supaya perusahaan bisa menilai lamaran Anda.');
        }
        abort_unless($job->status === 'aktif' && $job->closes_at->gte(today()), 404);

        $data = $request->validate(['message' => ['nullable', 'string', 'max:600']], [], ['message' => 'pesan']);

        $application = JobApplication::firstOrCreate(
            ['job_posting_id' => $job->id, 'talent_id' => $talent->id],
            ['message' => $data['message'] ?? null, 'status' => 'baru'],
        );

        return redirect()->route('talent.applications')->with('status', $application->wasRecentlyCreated
            ? "Lamaran untuk \"{$job->title}\" terkirim. Profil dan CV Anda bisa dilihat perusahaan."
            : 'Anda sudah melamar lowongan ini sebelumnya.');
    }
}
