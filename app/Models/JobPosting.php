<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['closes_at' => 'date'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereDate('closes_at', '>=', today());
    }

    /** Tenaga Ahli first, then soonest closing. */
    public function scopeFeaturedOrder(Builder $query): Builder
    {
        return $query->orderByRaw("case package when 'tenaga_ahli' then 0 when 'reguler' then 1 else 2 end")
            ->orderBy('closes_at');
    }

    public function packageLabel(): string
    {
        return config("kabelota.packages.{$this->package}.label");
    }

    public function isHighlighted(): bool
    {
        return $this->package === 'tenaga_ahli';
    }
}
