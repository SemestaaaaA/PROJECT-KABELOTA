<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Coba sebagai" buttons for client demos. Only active when KABELOTA_DEMO=true.
 * HRD signs into the seeded verified company; Talenta gets a fresh empty account
 * each time so every demo starts from an empty profile.
 */
class DemoSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('kabelota.demo_mode'), 404);
        $role = $request->validate(['role' => ['required', Rule::in(['perusahaan', 'talenta'])]])['role'];

        if ($role === 'perusahaan') {
            $user = User::where('email', 'hrd@demo.kabelota.test')->firstOrFail();
        } else {
            $user = User::create([
                'name' => 'Talenta Demo',
                'email' => 'talenta-'.Str::lower(Str::random(8)).'@demo.kabelota.test',
                'password' => Str::random(32),
                'role' => 'talenta',
            ]);
            $user->markEmailAsVerified();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $role === 'perusahaan'
            ? back()->with('status', 'Anda masuk sebagai HRD demo ('.$user->company->name.').')
            : redirect()->route('profile.edit')->with('status', 'Anda masuk sebagai talenta demo. Lengkapi profil supaya perusahaan bisa menemukan Anda.');
    }
}
