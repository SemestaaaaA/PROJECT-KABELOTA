<?php

namespace App\Filament\Resources\RecruitmentOffers\Widgets;

use App\Models\RecruitmentOffer;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RecruitmentOfferStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // "Tertarik" counts every offer the talent ever said yes to, including ones that moved on to a job offer.
        $c = RecruitmentOffer::query()
            ->selectRaw('count(*) as sent')
            ->selectRaw("sum(case when responded_at is not null and (status != 'ditolak' or job_offered_at is not null) then 1 else 0 end) as interested")
            ->selectRaw('sum(case when job_offered_at is not null then 1 else 0 end) as job_offers')
            ->selectRaw("sum(case when status = 'diambil' then 1 else 0 end) as taken")
            ->toBase()->first();

        $sent = (int) $c->sent;
        $interested = (int) $c->interested;

        return [
            Stat::make('Terkirim', $sent)->description('total tawaran dari perusahaan')->icon(Heroicon::OutlinedPaperAirplane),
            Stat::make('Tertarik', $interested)
                ->description($sent ? round($interested / $sent * 100).'% dari yang terkirim' : 'belum ada tawaran')
                ->icon(Heroicon::OutlinedHandThumbUp),
            Stat::make('Tawaran kerja', (int) $c->job_offers)->description('posisi dan tanggal mulai dikirim')->icon(Heroicon::OutlinedBriefcase),
            Stat::make('Diambil', (int) $c->taken)->description('talenta menerima')->color('success')->icon(Heroicon::OutlinedCheckBadge),
        ];
    }
}
