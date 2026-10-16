<?php

namespace App\Filament\Resources\Companies\Widgets;

use App\Models\Company;
use App\Models\JobPosting;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompanyStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $counts = Company::query()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'menunggu' then 1 else 0 end) as pending")
            ->selectRaw("sum(case when status = 'terverifikasi' then 1 else 0 end) as verified")
            ->toBase()->first();
        $pending = (int) $counts->pending;

        return [
            Stat::make('Total perusahaan', (int) $counts->total)->icon(Heroicon::OutlinedBuildingOffice2),
            Stat::make('Menunggu verifikasi', $pending)
                ->description($pending ? 'perlu dicek' : 'semua sudah dicek')
                ->color($pending ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedClock),
            Stat::make('Terverifikasi', (int) $counts->verified)->icon(Heroicon::OutlinedCheckBadge),
            Stat::make('Lowongan tayang', JobPosting::open()->count())->icon(Heroicon::OutlinedBriefcase),
        ];
    }
}
