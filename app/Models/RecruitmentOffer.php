<?php

namespace App\Models;

use App\Models\Concerns\HasJobOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    use HasJobOffer;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'responded_at' => 'datetime'];
    }

    // "diterima" keeps its database value but means "tertarik": the talent opened their contact, nothing is signed yet.
    public const STATUSES = [
        'menunggu' => 'Menunggu jawaban', 'diterima' => 'Tertarik', 'ditolak' => 'Ditolak',
        'tawaran_kerja' => 'Tawaran kerja', 'diambil' => 'Diambil', 'ditutup' => 'Ditutup', 'kedaluwarsa' => 'Kedaluwarsa',
    ];

    public const OPEN_STATUSES = ['menunggu', 'diterima', 'tawaran_kerja'];

    public const OFFERABLE_FROM = ['diterima', 'kedaluwarsa'];

    public const CONTACT_STATUSES = ['diterima', 'tawaran_kerja', 'diambil', 'kedaluwarsa'];

    public const ANSWERED_AT = 'responded_at';

    protected const BADGES = ['menunggu' => 'b-contract', 'diterima' => 'b-ok'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function typeKey(): string
    {
        return 'rekrut';
    }

    public function title(): string
    {
        return $this->position;
    }

    public function companyId(): ?int
    {
        return $this->company_id;
    }

    public function companyName(): string
    {
        return $this->company_name;
    }

    public function companyUser(): ?User
    {
        return $this->company?->user;
    }

    public function companyInboxUrl(): string
    {
        return route('company.offers');
    }

    public function talentInboxRoute(): string
    {
        return 'talent.offers';
    }
}
