<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

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
