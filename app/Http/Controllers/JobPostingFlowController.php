<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Demo of the paid job-posting flow: details -> package -> transfer proof ->
 * waiting for admin (approved in Filament) -> live.
 */
class JobPostingFlowController extends Controller
{
    /** Only a verified company may post; others are sent to finish their profile first. */
    private function ensureCompany(Request $request): ?RedirectResponse
    {
        return $request->user()->isVerifiedCompany()
            ? null
            : redirect()->route('company.profile')->with('status', 'Lengkapi profil perusahaan dan tunggu verifikasi admin sebelum memasang lowongan.');
    }

    private function company(Request $request): Company
    {
        return $request->user()->company;
    }

    public function create(Request $request): View|RedirectResponse
    {
        return $this->ensureCompany($request) ?? view('jobs.create', ['company' => $this->company($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureCompany($request)) {
            return $redirect;
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'concentration' => ['nullable', Rule::in(config('kabelota.concentrations'))],
            'min_jenjang' => ['nullable', Rule::in(array_keys(config('kabelota.jenjang')))],
            'min_experience' => ['nullable', 'integer', 'between:0,40'],
            'duration_months' => ['required', 'integer', 'between:1,36'],
            'location' => ['required', Rule::in(config('kabelota.locations'))],
            'description' => ['required', 'string', 'min:30', 'max:3000'],
            'package' => ['required', Rule::in(array_keys(config('kabelota.packages')))],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ], [
            'payment_proof.required' => 'Unggah bukti transfer supaya admin bisa memverifikasi.',
        ], [
            'title' => 'judul posisi', 'duration_months' => 'durasi', 'location' => 'lokasi',
            'description' => 'deskripsi', 'package' => 'paket', 'payment_proof' => 'bukti transfer',
        ]);

        $job = $this->company($request)->jobPostings()->create(collect($data)->except('payment_proof')->all() + [
            'status' => 'menunggu_verifikasi',
            'payment_proof_path' => $request->file('payment_proof')->store('payment-proofs', 'local'),
            'closes_at' => today()->addDays(config("kabelota.packages.{$data['package']}.days")),
        ]);

        return redirect()->route('jobs.posting.status', $job);
    }

    public function status(Request $request, JobPosting $job): View|RedirectResponse
    {
        if ($redirect = $this->ensureCompany($request)) {
            return $redirect;
        }
        abort_unless($job->company_id === $this->company($request)->id, 403);

        return view('jobs.status', ['job' => $job->load('company')]);
    }
}
