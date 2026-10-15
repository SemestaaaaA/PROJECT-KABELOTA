<?php

namespace App\Services;

use App\Enums\Availability;
use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use App\Notifications\JobOfferAnswered;
use App\Notifications\JobOfferExpired;
use App\Notifications\JobOfferReceived;
use App\Notifications\JobOfferReminder;
use App\Notifications\ProcessClosed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Final hiring step shared by recruitment offers (company first) and job applications (talent first).
 * A company offers a job; the talent takes one, and every other open process of that talent closes.
 */
class HiringDecision
{
    public const OFFER_DAYS = 7;

    public const REMIND_WHEN_DAYS_LEFT = 2;

    public static function find(string $type, int $id): RecruitmentOffer|JobApplication
    {
        return match ($type) {
            'rekrut' => RecruitmentOffer::findOrFail($id),
            'lamaran' => JobApplication::findOrFail($id),
            default => abort(404),
        };
    }

    /** Every process of this talent that is still open, with the relations the views and emails need. */
    public static function openFor(Talent $talent): Collection
    {
        return RecruitmentOffer::open()->with('company.user')->where('talent_id', $talent->id)->get()
            ->concat(JobApplication::open()->with('jobPosting.company.user')->where('talent_id', $talent->id)->get());
    }

    /** @param array{job_offer_position: string, job_offer_start_date: string, job_offer_note?: ?string} $data */
    public function offerJob(RecruitmentOffer|JobApplication $record, array $data): void
    {
        abort_unless($record->canOfferJob(), 422, 'Tawaran kerja hanya bisa dikirim setelah talenta tertarik atau lamarannya ditinjau.');

        $updated = $record->newQuery()->whereKey($record->getKey())->whereIn('status', $record::OFFERABLE_FROM)->update([
            'status' => 'tawaran_kerja',
            'job_offer_position' => $data['job_offer_position'],
            'job_offer_start_date' => $data['job_offer_start_date'],
            'job_offer_note' => $data['job_offer_note'] ?? null,
            'job_offered_at' => now(),
            'job_offer_expires_at' => now()->addDays(self::OFFER_DAYS),
            'job_offer_reminded_at' => null,
        ]);
        abort_if($updated === 0, 422, 'Tawaran kerja hanya bisa dikirim setelah talenta tertarik atau lamarannya ditinjau.');
        $record->refresh();

        $record->talent->user?->notify(new JobOfferReceived($record));
    }

    public function accept(RecruitmentOffer|JobApplication $record, ?string $farewell): void
    {
        [$chosen, $closed] = DB::transaction(function () use ($record, $farewell) {
            // Lock the talent row so two "Ambil" clicks are handled one after the other.
            $talent = Talent::whereKey($record->talent_id)->lockForUpdate()->firstOrFail();
            $chosen = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($chosen->isJobOffer() && $chosen->job_offer_expires_at?->isFuture(), 422, 'Tawaran kerja ini sudah tidak berlaku.');

            $chosen->update(['status' => 'diambil', $chosen::ANSWERED_AT => now()]);

            $closed = self::openFor($talent)->reject(fn ($other) => $other->is($chosen))->values();
            foreach ($closed as $other) {
                $other->update(['status' => 'ditutup', 'closed_at' => now(), 'closed_note' => $farewell]);
            }

            $talent->update(['availability' => Availability::TerikatKontrak]);

            return [$chosen, $closed];
        });

        $chosen->companyUser()?->notify(new JobOfferAnswered($chosen));

        // One email per company, listing every process of theirs that closed. The hiring company already got JobOfferAnswered.
        $closed->groupBy(fn ($r) => $r->companyId())->each(function (Collection $records, $companyId) use ($chosen, $farewell) {
            if ((string) $companyId === (string) $chosen->companyId()) {
                return;
            }

            $records->first()->companyUser()?->notify(
                new ProcessClosed($chosen->talent->name, $records->map->title()->unique()->values()->all(), $farewell)
            );
        });
    }

    public function decline(RecruitmentOffer|JobApplication $record, ?string $note): void
    {
        abort_unless($record->isJobOffer() && $record->job_offer_expires_at?->isFuture(), 422, 'Tawaran kerja ini sudah tidak berlaku.');

        $updated = $record->newQuery()->whereKey($record->getKey())->where('status', 'tawaran_kerja')
            ->update(['status' => 'ditolak', $record::ANSWERED_AT => now(), 'closed_note' => $note]);
        abort_if($updated === 0, 422, 'Tawaran kerja ini sudah tidak berlaku.');

        $record->refresh()->companyUser()?->notify(new JobOfferAnswered($record));
    }

    /** Remind the talent once, when two days are left. */
    public function remindDue(): int
    {
        $count = 0;
        foreach ($this->pendingQueries() as $query) {
            $query->whereNull('job_offer_reminded_at')
                ->whereBetween('job_offer_expires_at', [now(), now()->addDays(self::REMIND_WHEN_DAYS_LEFT)])
                ->lazyById()->each(function ($record) use (&$count) {
                    $record->talent->user?->notify(new JobOfferReminder($record));
                    $record->update(['job_offer_reminded_at' => now()]);
                    $count++;
                });
        }

        return $count;
    }

    public function expireDue(): int
    {
        $count = 0;
        foreach ($this->pendingQueries() as $query) {
            $query->where('job_offer_expires_at', '<', now())->lazyById()->each(function ($record) use (&$count) {
                $record->update(['status' => 'kedaluwarsa']);
                $record->companyUser()?->notify(new JobOfferExpired($record));
                $count++;
            });
        }

        return $count;
    }

    /** @return list<\Illuminate\Database\Eloquent\Builder> */
    private function pendingQueries(): array
    {
        return [
            RecruitmentOffer::with(['talent.user', 'company.user'])->where('status', 'tawaran_kerja'),
            JobApplication::with(['talent.user', 'jobPosting.company.user'])->where('status', 'tawaran_kerja'),
        ];
    }
}
