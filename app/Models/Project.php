<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $guarded = [];

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function period(): string
    {
        return $this->year_end && $this->year_end !== $this->year_start
            ? "{$this->year_start}-{$this->year_end}"
            : (string) $this->year_start;
    }
}
