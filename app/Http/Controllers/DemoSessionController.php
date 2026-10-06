<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Demo-only "login": stores a role in the session so the client can try
 * gated features (Ajukan Rekrut, Lamar) before real auth exists in Fase 1.
 */
class DemoSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', Rule::in(['perusahaan', 'talenta'])]])['role'];
        $request->session()->regenerate();
        $request->session()->put('demo_role', $role);

        if ($role === 'talenta' && ! $request->session()->has('my_talent_id')) {
            return redirect()->route('profile.edit')->with('status', 'Anda masuk sebagai talenta demo. Lengkapi profil supaya perusahaan bisa menemukan Anda.');
        }

        return back()->with('status', $role === 'perusahaan'
            ? 'Anda masuk sebagai HRD demo (CV Lembah Palu Konsultan).'
            : 'Anda masuk sebagai talenta demo.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['demo_role', 'my_talent_id']);
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
