<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(function (Company $c) {
            if (! $c->slug) {
                $c->updateQuietly(['slug' => \Illuminate\Support\Str::slug($c->name).'-'.$c->id]);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path) : null;
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RecruitmentOffer::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'terverifikasi';
    }

    public function statusLabel(): string
    {
        return ['menunggu' => 'Menunggu verifikasi', 'terverifikasi' => 'Terverifikasi', 'ditolak' => 'Ditolak'][$this->status] ?? $this->status;
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }
}
