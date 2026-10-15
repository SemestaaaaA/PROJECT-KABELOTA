<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared final-job-offer behaviour for RecruitmentOffer and JobApplication.
 * The model defines STATUSES, OPEN_STATUSES, OFFERABLE_FROM, CONTACT_STATUSES, ANSWERED_AT and BADGES.
 */
trait HasJobOffer
{
    public function initializeHasJobOffer(): void
    {
        $this->mergeCasts([
            'job_offer_start_date' => 'date',
            'job_offered_at' => 'datetime',
            'job_offer_expires_at' => 'datetime',
            'job_offer_reminded_at' => 'datetime',
            'closed_at' => 'datetime',
        ]);
    }

    /** Processes that still need someone to act. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', static::OPEN_STATUSES);
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return (static::BADGES + [
            'tawaran_kerja' => 'b-offer', 'diambil' => 'b-ok', 'ditolak' => 'b-bad', 'ditutup' => 'b-off', 'kedaluwarsa' => 'b-off',
        ])[$this->status] ?? 'b-plain';
    }

    public function isJobOffer(): bool
    {
        return $this->status === 'tawaran_kerja';
    }

    public function canOfferJob(): bool
    {
        return in_array($this->status, static::OFFERABLE_FROM, true);
    }

    public function contactVisible(): bool
    {
        return in_array($this->status, static::CONTACT_STATUSES, true);
    }

    public function jobOfferDaysLeft(): int
    {
        return $this->job_offer_expires_at
            ? max(0, (int) ceil(now()->diffInHours($this->job_offer_expires_at, false) / 24))
            : 0;
    }

    abstract public function typeKey(): string;

    abstract public function title(): string;

    abstract public function companyId(): ?int;

    abstract public function companyName(): string;

    abstract public function companyUser(): ?User;

    abstract public function companyInboxUrl(): string;

    abstract public function talentInboxRoute(): string;
}
