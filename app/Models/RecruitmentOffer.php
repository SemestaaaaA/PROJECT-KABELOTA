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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
