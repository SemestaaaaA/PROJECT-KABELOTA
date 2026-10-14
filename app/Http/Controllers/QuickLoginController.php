<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Coba sebagai" buttons for demos and QA. Only active when KABELOTA_QUICK_LOGIN=true.
 * HRD signs into one shared, already-verified company (created on first use, so it also
 * works on a server that only has the admin account). Talenta gets a fresh empty account
 * every time, so each try starts from an empty profile.
 */
class QuickLoginController extends Controller
{
    public const HRD_EMAIL = 'hrd@demo.kabelota.test';

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('kabelota.quick_login'), 404);
        $role = $request->validate(['role' => ['required', Rule::in(['perusahaan', 'talenta'])]])['role'];

        $user = $role === 'perusahaan' ? $this->hrd() : $this->freshTalent();

        Auth::login($user);
        $request->session()->regenerate();

        return $role === 'perusahaan'
            ? back()->with('status', 'Anda masuk sebagai HRD contoh ('.$user->company->name.').')
            : redirect()->route('profile.edit')->with('status', 'Anda masuk sebagai talenta contoh. Lengkapi profil supaya perusahaan bisa menemukan Anda.');
    }

    private function hrd(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::HRD_EMAIL],
            ['name' => 'HRD Contoh', 'role' => 'perusahaan', 'password' => Str::random(32), 'email_verified_at' => now()],
        );

        if (! $user->company) {
            Company::create([
                'user_id' => $user->id, 'name' => 'PT Contoh Konstruksi Sulteng', 'type' => 'kontraktor', 'city' => 'Palu',
                'status' => 'terverifikasi', 'verified_at' => now(), 'contact_name' => 'HRD Contoh',
            ]);
            $user->refresh();
        }

        return $user;
    }

    private function freshTalent(): User
    {
        $user = User::create([
            'name' => 'Talenta Contoh',
            'email' => 'talenta-'.Str::lower(Str::random(8)).'@demo.kabelota.test',
            'password' => Str::random(32),
            'role' => 'talenta',
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}
