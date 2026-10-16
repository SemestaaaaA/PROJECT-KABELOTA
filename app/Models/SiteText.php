<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin override for one `<x-cms>` text. Written only through App\Support\SiteText. */
class SiteText extends Model
{
    protected $fillable = ['key', 'html', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
