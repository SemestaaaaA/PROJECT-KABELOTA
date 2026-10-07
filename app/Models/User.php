<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consented_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // Auth emails go through the queue (see QueuedVerifyEmail / QueuedResetPassword).
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    // Admin two-factor login (authenticator app) for the Filament panel.
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    public function talent(): HasOne
    {
        return $this->hasOne(Talent::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    /** Ids of jobs this talent already applied to (memoized per request). */
    public function appliedJobIds(): array
    {
        return once(fn () => $this->isTalent()
            ? JobApplication::whereHas('talent', fn ($q) => $q->where('user_id', $this->id))->pluck('job_posting_id')->all()
            : []);
    }

    /** Offers still waiting for this talent's answer (nav badge). */
    public function pendingOfferCount(): int
    {
        return once(fn () => $this->isTalent()
            ? RecruitmentOffer::where('status', 'menunggu')->whereHas('talent', fn ($q) => $q->where('user_id', $this->id))->count()
            : 0);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTalent(): bool
    {
        return $this->role === 'talenta';
    }

    public function isCompany(): bool
    {
        return $this->role === 'perusahaan';
    }

    /** A company account that admin has verified (may recruit, post jobs, open documents). */
    public function isVerifiedCompany(): bool
    {
        return $this->isCompany() && $this->company?->isVerified();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
