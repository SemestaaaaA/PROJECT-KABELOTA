<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Account settings: password, email, profile visibility, and self-service deletion (UU PDP). */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'talent' => Talent::where('user_id', $user->id)->first(),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => 'kata sandi sekarang', 'password' => 'kata sandi baru']);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Kata sandi sudah diganti.');
    }

    public function email(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('email', [
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ], [], ['email' => 'email baru', 'current_password' => 'kata sandi']);

        if ($data['email'] === $user->email) {
            return back()->with('status', 'Email tidak berubah.');
        }

        $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('status', 'Email diganti. Buka link verifikasi yang kami kirim ke alamat baru.');
    }

    /** UU PDP right of access: everything Kabelota stores about this person, as JSON. */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $talent = Talent::with(['certifications', 'projects', 'offers', 'applications.jobPosting'])->where('user_id', $user->id)->first();
        $company = $user->company?->load('jobPostings');

        $data = [
            'diunduh_pada' => now()->toIso8601String(),
            'akun' => $user->only(['name', 'email', 'role', 'created_at', 'email_verified_at', 'consented_at']),
            'profil_talenta' => $talent?->makeVisible(['email', 'phone'])->makeHidden(['cv_path', 'skk_scan_path', 'transcript_path'])->toArray(),
            'perusahaan' => $company?->makeHidden(['legal_doc_path'])->toArray(),
            'catatan' => 'File CV, scan SKK, transkrip, dan dokumen legalitas bisa diunduh dari profil masing-masing.',
        ];

        return response()->streamDownload(
            fn () => print(json_encode(array_filter($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'data-kabelota-'.now()->format('Ymd').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function visibility(Request $request): RedirectResponse
    {
        $talent = Talent::where('user_id', $request->user()->id)->firstOrFail();
        $talent->update(['is_visible' => $request->boolean('is_visible')]);

        return back()->with('status', $talent->is_visible
            ? 'Profil Anda tampil lagi di pencarian talenta.'
            : 'Profil Anda disembunyikan. Perusahaan tidak bisa menemukan atau mengirim tawaran baru.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403, 'Akun admin dihapus lewat pengelola sistem.');

        $request->validateWithBag('delete', [
            'current_password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:HAPUS'],
        ], ['confirm.in' => 'Ketik HAPUS dengan huruf kapital.'], ['current_password' => 'kata sandi', 'confirm' => 'konfirmasi']);

        $files = ['public' => [], 'local' => []];

        // Log out first: logout() cycles the remember token, which would save (re-insert) a deleted user.
        Auth::logout();

        DB::transaction(function () use ($user, &$files) {
            // Talents and companies only null their user_id on delete, so remove them explicitly.
            // Certifications, projects, offers, applications and job postings cascade from there.
            if ($talent = Talent::where('user_id', $user->id)->first()) {
                $files['public'][] = $talent->photo_path;
                array_push($files['local'], $talent->cv_path, $talent->skk_scan_path, $talent->transcript_path);
                $talent->delete();
            }

            if ($company = $user->company) {
                $files['public'][] = $company->logo_path;
                $files['local'][] = $company->legal_doc_path;
                array_push($files['local'], ...JobPosting::where('company_id', $company->id)->pluck('payment_proof_path')->all());
                $company->delete();
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        foreach ($files as $disk => $paths) {
            Storage::disk($disk)->delete(array_values(array_filter($paths)));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Akun dan semua data Anda sudah dihapus. Terima kasih sudah memakai Kabelota.');
    }
}
