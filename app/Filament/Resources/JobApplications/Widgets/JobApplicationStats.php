<?php

namespace App\Filament\Resources\JobApplications\Widgets;

use App\Models\JobApplication;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JobApplicationStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $c = JobApplication::query()
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as recent', [now()->subDays(30)])
            ->selectRaw("sum(case when status = 'baru' then 1 else 0 end) as fresh")
            ->selectRaw("sum(case when status = 'tawaran_kerja' then 1 else 0 end) as offers")
            ->selectRaw("sum(case when status = 'diambil' then 1 else 0 end) as taken")
            ->toBase()->first();
        $fresh = (int) $c->fresh;

        return [
            Stat::make('Lamaran 30 hari', (int) $c->recent)->icon(Heroicon::OutlinedInboxArrowDown),
            Stat::make('Baru belum ditinjau', $fresh)
                ->description($fresh ? 'menunggu perusahaan membuka' : 'semua sudah ditinjau')
                ->color($fresh ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedEnvelope),
            Stat::make('Tawaran kerja berjalan', (int) $c->offers)
                ->description('menunggu jawaban talenta')
                ->icon(Heroicon::OutlinedHandRaised),
            Stat::make('Diambil', (int) $c->taken)
                ->description('talenta menerima tawaran')
                ->color('success')
                ->icon(Heroicon::OutlinedCheckBadge),
        ];
    }
}
