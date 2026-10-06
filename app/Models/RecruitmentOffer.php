<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'responded_at' => 'datetime'];
    }

    public const STATUSES = ['menunggu' => 'Menunggu jawaban', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return ['menunggu' => 'b-contract', 'diterima' => 'b-ok', 'ditolak' => 'b-bad'][$this->status] ?? 'b-plain';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
