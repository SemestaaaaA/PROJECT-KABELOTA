<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecruitmentOfferController extends Controller
{
    public function store(Request $request, Talent $talent): RedirectResponse
    {
        $company = $request->user()->company;
        if (! $company?->isVerified()) {
            return redirect()->route('company.profile')->with('status', 'Ajukan Rekrut terbuka setelah perusahaan Anda diverifikasi admin.');
        }

        abort_if($talent->availability === Availability::TidakTersedia, 422, 'Talenta sedang tidak menerima tawaran.');

        $data = $request->validateWithBag('offer', [
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:80'],
            'contact_email' => ['required', 'email', 'max:120'],
            'position' => ['required', 'string', 'max:160'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'duration' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:20', 'max:1500'],
        ], [], [
            'company_name' => 'nama perusahaan',
            'contact_name' => 'nama HRD',
            'contact_email' => 'email HRD',
            'position' => 'posisi',
            'start_date' => 'tanggal mulai',
            'message' => 'pesan',
        ]);

        $offer = $talent->offers()->create($data + ['company_id' => $company->id]);
        $talent->user?->notify(new \App\Notifications\OfferReceived($offer));

        // Fase 1: kirim email ke talenta lewat queue. Untuk demo, tawaran hanya disimpan.
        return redirect()
            ->route('talents.show', $talent)
            ->with('offer_sent', $data['position']);
    }
}
