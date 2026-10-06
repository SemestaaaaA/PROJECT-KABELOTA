<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date'];
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
