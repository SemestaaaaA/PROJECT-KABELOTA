<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/** Audit trail of important admin decisions. Written only through record(). */
class AdminActivity extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'company_verified' => 'Perusahaan diverifikasi',
        'company_rejected' => 'Perusahaan ditolak',
        'job_approved' => 'Lowongan disetujui',
        'job_rejected' => 'Lowongan ditolak',
        'skk_verified' => 'SKK diverifikasi',
        'skk_rejected' => 'SKK ditolak',
        'text_edited' => 'Teks situs diubah',
        'text_restored' => 'Teks situs dikembalikan',
    ];

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description'];

    /** $userId defaults to the logged-in admin; pass it when acting outside a login session (console, jobs). */
    public static function record(string $action, Model $subject, string $description, ?int $userId = null): void
    {
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => Str::limit($description, 297),
        ]);
    }

    /** "Subject. Alasan: ..." for rejections; the reason is cut at 200 characters so the line fits the column. */
    public static function withReason(string $subject, ?string $reason): string
    {
        return filled($reason) ? rtrim($subject, '.').'. Alasan: '.Str::limit(trim($reason), 200) : $subject;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
