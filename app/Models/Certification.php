<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function jenjangLabel(): string
    {
        return config('kabelota.jenjang')[$this->jenjang] ?? (string) $this->jenjang;
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }
}
