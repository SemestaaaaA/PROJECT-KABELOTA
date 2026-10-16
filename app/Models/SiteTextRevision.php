<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The html a change replaced (null = the default was showing), so it can be restored. */
class SiteTextRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['key', 'html', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
