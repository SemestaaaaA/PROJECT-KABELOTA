<?php

namespace App\Filament\Resources\Talent\Widgets;

use App\Models\Talent;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TalentStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $c = Talent::query()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when type = 'alumni' then 1 else 0 end) as alumni")
            ->selectRaw("sum(case when type = 'mahasiswa' then 1 else 0 end) as students")
            ->selectRaw("sum(case when availability = 'tersedia' then 1 else 0 end) as available")
            // Same condition as the "SKK perlu dicek" table filter.
            ->selectRaw('sum(case when skk_scan_path is not null and skk_verified_at is null and skk_review_note is null then 1 else 0 end) as skk_queue')
            ->selectRaw('sum(case when cv_path is not null then 1 else 0 end) as with_cv')
            ->toBase()->first();

        $total = (int) $c->total;
        $queue = (int) $c->skk_queue;
        $withCv = (int) $c->with_cv;

        return [
            Stat::make('Total talenta', $total)
                ->description((int) $c->alumni.' alumni · '.(int) $c->students.' mahasiswa')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Tersedia', (int) $c->available)
                ->description('siap bekerja sekarang')
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make('SKK perlu dicek', $queue)
                ->description($queue ? 'scan menunggu verifikasi' : 'tidak ada antrean')
                ->color($queue ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass),
            Stat::make('Punya CV', ($total ? round($withCv / $total * 100) : 0).'%')
                ->description("$withCv dari $total")
                ->icon(Heroicon::OutlinedDocumentText),
        ];
    }
}
