<?php

namespace App\Models;

use App\Enums\Availability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Talent extends Model
{
    use HasFactory;

    protected $table = 'talents';

    protected $guarded = [];

    /** Contact data never leaves the server until a talent accepts an offer. */
    protected $hidden = ['email', 'phone', 'cv_path', 'skk_scan_path', 'transcript_path'];

    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'preferred_locations' => 'array',
            'skills' => 'array',
            'gpa' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class)->orderByDesc('jenjang');
    }

    public function primaryCertification(): HasOne
    {
        return $this->hasOne(Certification::class)->ofMany('jenjang', 'max');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderByDesc('year_start');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RecruitmentOffer::class);
    }

    public function isAlumni(): bool
    {
        return $this->type === 'alumni';
    }

    public function experienceYears(): ?int
    {
        return $this->experience_since ? max(0, now()->year - $this->experience_since) : null;
    }

    public function hmtsLabel(): string
    {
        return config('kabelota.hmts_statuses')[$this->hmts_status] ?? 'Pasif';
    }

    public function isHmtsActive(): bool
    {
        return $this->hmts_status !== 'pasif';
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Checklist used by the profile builder and the "Ini profil Anda" card. */
    public function completeness(): array
    {
        $items = [
            'Foto profil' => (bool) $this->photo_path,
            'Ringkasan singkat' => filled($this->bio),
            'CV (PDF)' => (bool) $this->cv_path,
            'Keahlian software' => count($this->skills ?? []) > 0,
            $this->isAlumni() ? 'Sertifikat SKK' : 'Transkrip nilai' => $this->isAlumni()
                ? $this->certifications()->exists()
                : (bool) $this->transcript_path,
            $this->isAlumni() ? 'Riwayat proyek' : 'Organisasi atau kerja praktik' => $this->projects()->exists(),
        ];

        return ['items' => $items, 'percent' => (int) round(collect($items)->filter()->count() / count($items) * 100)];
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', preg_replace('/^(Muh\.|Moh\.|Hj\.)\s*/', '', $this->name));

        return mb_strtoupper(collect($words)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }

    /** Apply the talent-search filters from a request query array. */
    public function scopeSearch(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('headline', 'like', "%{$term}%")
                ->orWhereHas('projects', fn ($p) => $p->where('name', 'like', "%{$term}%"))))
            ->when($f['tipe'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($f['konsentrasi'] ?? null, fn ($q, $k) => $q->where('concentration', $k))
            ->when($f['jabatan'] ?? null, fn ($q, $j) => $q->whereHas('certifications', fn ($c) => $c->where('jabatan_kerja', $j)))
            ->when($f['jenjang'] ?? null, fn ($q, $levels) => $q->whereHas('certifications', fn ($c) => $c->whereIn('jenjang', (array) $levels)))
            ->when($f['pengalaman'] ?? null, fn ($q, $min) => $q->where('experience_since', '<=', now()->year - (int) $min))
            ->when($f['lokasi'] ?? null, fn ($q, $loc) => $q->where(fn ($w) => $w
                ->where('city', $loc)
                ->orWhereJsonContains('preferred_locations', $loc)))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->whereIn('availability', (array) $s));
    }
}
