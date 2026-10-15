<?php

namespace App\Filament\Resources\JobPostings\Widgets;

use App\Filament\Widgets\Chart;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JobPostingStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $waiting = JobPosting::where('status', 'menunggu_verifikasi')->whereNotNull('payment_proof_path')->count();

        // One row per package, then price x count: no per-posting loop.
        $revenue = JobPosting::query()
            ->whereBetween('approved_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('package')
            ->selectRaw('package, count(*) as n')
            ->toBase()->get()
            ->sum(fn ($row) => $row->n * (int) config("kabelota.packages.{$row->package}.price", 0));

        return [
            Stat::make('Menunggu cek bayar', $waiting)
                ->description($waiting ? 'bukti transfer perlu dicek' : 'tidak ada antrean')
                ->color($waiting ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Tayang', JobPosting::open()->count())->icon(Heroicon::OutlinedBriefcase),
            Stat::make('Pendapatan bulan ini', Chart::rupiah($revenue))
                ->description('dari lowongan yang disetujui '.now()->translatedFormat('F Y'))
                ->icon(Heroicon::OutlinedCurrencyDollar),
            Stat::make('Total pelamar', JobApplication::count())->icon(Heroicon::OutlinedInboxArrowDown),
        ];
    }
}
