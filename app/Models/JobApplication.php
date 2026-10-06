<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    public const STATUSES = ['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return ['baru' => 'b-new', 'ditinjau' => 'b-contract', 'diterima' => 'b-ok', 'ditolak' => 'b-bad'][$this->status] ?? 'b-plain';
    }
}
