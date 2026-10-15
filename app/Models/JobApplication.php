<?php

namespace App\Models;

use App\Models\Concerns\HasJobOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use HasJobOffer;

    public const STATUSES = [
        'baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditolak' => 'Ditolak',
        'tawaran_kerja' => 'Tawaran kerja', 'diambil' => 'Diambil', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa',
    ];

    /** Statuses a company may set with the dropdown. Anything else goes through HiringDecision. */
    public const COMPANY_STATUSES = ['baru', 'ditinjau', 'ditolak'];

    public const OPEN_STATUSES = ['baru', 'ditinjau', 'tawaran_kerja'];

    public const OFFERABLE_FROM = ['ditinjau', 'kedaluwarsa'];

    public const CONTACT_STATUSES = ['tawaran_kerja', 'diambil'];

    public const ANSWERED_AT = 'status_changed_at';

    protected const BADGES = ['baru' => 'b-new', 'ditinjau' => 'b-contract'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function typeKey(): string
    {
        return 'lamaran';
    }

    public function title(): string
    {
        return $this->jobPosting?->title ?? '-';
    }

    public function companyId(): ?int
    {
        return $this->jobPosting?->company_id;
    }

    public function companyName(): string
    {
        return $this->jobPosting?->company?->name ?? '-';
    }

    public function companyUser(): ?User
    {
        return $this->jobPosting?->company?->user;
    }

    public function companyInboxUrl(): string
    {
        return route('company.applicants', $this->job_posting_id);
    }

    public function talentInboxRoute(): string
    {
        return 'talent.applications';
    }
}
