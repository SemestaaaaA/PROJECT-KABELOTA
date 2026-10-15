<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Services\HiringDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Talent side: incoming offers (Tertarik/Tolak) and own job applications. */
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
            'offers' => $talent->offers()->with('company')->orderByRaw("status = 'tawaran_kerja' desc, status = 'menunggu' desc")->latest()->get(),
            'openProcesses' => HiringDecision::openFor($talent),
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
        $offer->company?->user?->notify(new \App\Notifications\OfferAnswered($offer));

        return redirect()->route('talent.offers')->with('status', $data['decision'] === 'diterima'
            ? "Anda tertarik dengan tawaran {$offer->company_name}. Nomor HP dan email Anda sekarang terlihat oleh perusahaan ini."
            : "Tawaran dari {$offer->company_name} ditolak. Kontak Anda tetap tersembunyi.");
    }

    public function applications(Request $request): View|RedirectResponse
    {
        if (! $talent = $this->talent($request)) {
            return $this->toProfile();
        }

        return view('talent.applications', [
            'applications' => $talent->applications()->with('jobPosting.company')->orderByRaw("status = 'tawaran_kerja' desc")->latest()->get(),
            'openProcesses' => HiringDecision::openFor($talent),
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

        if ($application->wasRecentlyCreated) {
            $job->company->user?->notify(new \App\Notifications\ApplicationReceived($application));
        }

        return redirect()->route('talent.applications')->with('status', $application->wasRecentlyCreated
            ? "Lamaran untuk \"{$job->title}\" terkirim. Profil dan CV Anda bisa dilihat perusahaan."
            : 'Anda sudah melamar lowongan ini sebelumnya.');
    }

    public function acceptJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = $this->ownJobOffer($request, $type, $id);
        $data = $request->validate(['farewell' => ['nullable', 'string', 'max:500']], [], ['farewell' => 'pesan']);

        try {
            $hiring->accept($record, $data['farewell'] ?? null);
        } catch (HttpException $e) {
            abort_unless($e->getStatusCode() === 422, $e->getStatusCode(), $e->getMessage());

            return redirect()->route($record->talentInboxRoute())->with('status', $e->getMessage());
        }

        return redirect()->route($record->talentInboxRoute())->with('status',
            "Selamat! Anda mengambil tawaran {$record->job_offer_position} di {$record->companyName()}. Proses dengan perusahaan lain sudah ditutup dan status Anda kini Terikat kontrak.");
    }

    public function declineJob(Request $request, string $type, int $id, HiringDecision $hiring): RedirectResponse
    {
        $record = $this->ownJobOffer($request, $type, $id);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']], [], ['note' => 'alasan']);

        try {
            $hiring->decline($record, $data['note'] ?? null);
        } catch (HttpException $e) {
            abort_unless($e->getStatusCode() === 422, $e->getStatusCode(), $e->getMessage());

            return redirect()->route($record->talentInboxRoute())->with('status', $e->getMessage());
        }

        return redirect()->route($record->talentInboxRoute())->with('status', "Tawaran kerja dari {$record->companyName()} ditolak.");
    }

    private function ownJobOffer(Request $request, string $type, int $id): RecruitmentOffer|JobApplication
    {
        $record = HiringDecision::find($type, $id);
        abort_unless($record->talent?->user_id === $request->user()->id, 403);

        return $record;
    }
}
