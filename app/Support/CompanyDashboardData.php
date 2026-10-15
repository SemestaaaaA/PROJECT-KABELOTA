<?php

namespace App\Support;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Everything the company dashboard shows, computed for one company. */
class CompanyDashboardData
{
    public function __construct(public Company $company) {}

    /** @return array{items: list<array{icon: string, text: string, url: string}>, total: int} */
    public function todo(int $limit = 6): array
    {
        $items = [];

        $newByJob = $this->applications()->where('status', 'baru')->with('jobPosting')->oldest()->get()->groupBy('job_posting_id');
        foreach ($newByJob as $apps) {
            $job = $apps->first()->jobPosting;
            $items[] = ['icon' => 'ph-user-plus', 'text' => $apps->count().' pelamar baru di '.$job->title, 'url' => route('company.applicants', [$job, 'status' => 'baru'])];
        }

        foreach ($this->company->offers()->where('status', 'diterima')->with('talent')->latest('responded_at')->get() as $offer) {
            $items[] = ['icon' => 'ph-hand-heart', 'text' => Str::before($offer->talent->name, ',').' tertarik dengan tawaran '.$offer->position.'. Kirim tawaran kerja?', 'url' => route('company.offers')];
        }

        $expired = $this->company->offers()->where('status', 'kedaluwarsa')->with('talent')->get()
            ->concat($this->applications()->where('status', 'kedaluwarsa')->with('talent')->get())
            ->sortByDesc('updated_at');
        foreach ($expired as $record) {
            $items[] = ['icon' => 'ph-hourglass-low', 'text' => 'Tawaran kerja untuk '.Str::before($record->talent->name, ',').' kedaluwarsa. Kirim ulang?', 'url' => $record->companyInboxUrl()];
        }

        return ['items' => array_slice($items, 0, $limit), 'total' => count($items)];
    }

    /** @return array{pending: int, nearest: ?\Illuminate\Support\Carbon, taken: int} */
    public function jobOffers(): array
    {
        $offers = $this->company->offers()->where('status', 'tawaran_kerja');
        $apps = $this->applications()->where('status', 'tawaran_kerja');
        $nearest = collect([
            (clone $offers)->where('job_offer_expires_at', '>', now())->min('job_offer_expires_at'),
            (clone $apps)->where('job_offer_expires_at', '>', now())->min('job_offer_expires_at'),
        ])->filter()->min();

        return [
            'pending' => (clone $offers)->count() + (clone $apps)->count(),
            'nearest' => $nearest ? \Illuminate\Support\Carbon::parse($nearest) : null,
            'taken' => $this->company->offers()->where('status', 'diambil')->count() + $this->applications()->where('status', 'diambil')->count(),
        ];
    }

    /** @return array{live: int, applicants: int, closingSoon: ?\App\Models\JobPosting} */
    public function jobs(): array
    {
        return [
            'live' => $this->company->jobPostings()->open()->count(),
            'applicants' => $this->applications()->whereHas('jobPosting', fn (Builder $q) => $q->open())->count(),
            'closingSoon' => $this->company->jobPostings()->open()->orderBy('closes_at')->first(),
        ];
    }

    public function openContacts(): int
    {
        // Unique talents: one person with an offer and an application is still one open contact.
        return $this->company->offers()->whereIn('status', RecruitmentOffer::CONTACT_STATUSES)->pluck('talent_id')
            ->merge($this->applications()->whereIn('status', JobApplication::CONTACT_STATUSES)->pluck('talent_id'))
            ->unique()->count();
    }

    /** @return array{verified: bool, label: string, percent: int, missing: list<string>} */
    public function profile(): array
    {
        $c = $this->company;
        $checks = ['Logo' => filled($c->logo_path), 'Tentang perusahaan' => filled($c->about), 'Website' => filled($c->website), 'NIB / SBU' => filled($c->legal_doc_path) || filled($c->nib)];

        return [
            'verified' => $c->isVerified(),
            'label' => $c->statusLabel(),
            'percent' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn (bool $done) => ! $done)),
        ];
    }

    private function applications(): Builder
    {
        return JobApplication::query()->whereHas('jobPosting', fn (Builder $q) => $q->where('company_id', $this->company->id));
    }
}
