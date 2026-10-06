<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Company profile + legal documents. Saving a rejected profile sends it back to the admin queue. */
class CompanyProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('company.profile', ['company' => $request->user()->company]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['konsultan', 'kontraktor', 'pemilik_proyek', 'lainnya'])],
            'city' => ['required', Rule::in(config('kabelota.locations'))],
            'nib' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:120'],
            'about' => ['nullable', 'string', 'max:800'],
            'contact_name' => ['required', 'string', 'max:80'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('kabelota.upload.photo_kb')],
            'legal_doc' => [$company->legal_doc_path ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:'.config('kabelota.upload.document_kb')],
        ], ['legal_doc.required' => 'Unggah NIB atau SBU (PDF) supaya admin bisa memverifikasi.'], [
            'name' => 'nama perusahaan', 'type' => 'jenis', 'city' => 'kota', 'nib' => 'NIB',
            'about' => 'profil singkat', 'contact_name' => 'nama penanggung jawab', 'contact_phone' => 'nomor HP',
            'logo' => 'logo', 'legal_doc' => 'dokumen legalitas',
        ]);

        if ($request->hasFile('logo')) {
            $company->logo_path && Storage::disk('public')->delete($company->logo_path);
            $company->logo_path = $request->file('logo')->store('logos', 'public');
        }
        if ($request->hasFile('legal_doc')) {
            $company->legal_doc_path && Storage::disk('local')->delete($company->legal_doc_path);
            $company->legal_doc_path = $request->file('legal_doc')->store('legal-docs', 'local');
        }

        $company->fill(collect($data)->except(['logo', 'legal_doc'])->all());
        if ($company->status === 'ditolak') {
            $company->status = 'menunggu';
            $company->rejection_reason = null;
        }
        $company->save();

        return redirect()->route('company.profile')->with('status', $company->isVerified()
            ? 'Profil perusahaan tersimpan.'
            : 'Profil tersimpan dan masuk antrean verifikasi admin.');
    }
}
