<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** No standalone page: the login popup lives in the layout. */
    public function showLogin(): RedirectResponse
    {
        return redirect()->route('home')->with('open_auth', 'masuk');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validateWithBag('login', [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['password' => 'kata sandi']);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Log::warning('Login dikunci karena terlalu banyak percobaan', ['email' => Str::lower($credentials['email']), 'ip' => $request->ip()]);
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.'])->errorBag('login');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.'])->errorBag('login');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user()));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('register', [
            'role' => ['required', Rule::in(['talenta', 'perusahaan'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')],
            'password' => ['required', Password::min(8)],
            'consent' => ['accepted'],
            'kb_trap' => ['prohibited'],
        ], ['consent.accepted' => 'Centang persetujuan data untuk melanjutkan.', 'kb_trap.prohibited' => 'Pendaftaran tidak bisa diproses.'], ['name' => 'nama', 'password' => 'kata sandi']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);
        $user->forceFill(['consented_at' => now()])->save();

        if ($user->isCompany()) {
            Company::create(['user_id' => $user->id, 'name' => $data['name'], 'type' => 'kontraktor', 'city' => 'Palu', 'status' => 'menunggu']);
        }

        event(new Registered($user)); // sends the verification email (MAIL_MAILER=log in local)
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public static function homeFor(User $user): string
    {
        return match ($user->role) {
            'admin' => '/admin',
            'perusahaan' => $user->isVerifiedCompany() ? route('company.jobs') : route('company.profile'),
            default => $user->talent ? route('talents.show', $user->talent) : route('profile.edit'),
        };
    }
}
