<?php

namespace App\Filament\Widgets;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Filament\Widgets\ChartWidget;

/** Where recruitment stalls: each bar is one step, read top to bottom. */
class RecruitmentFunnelChart extends ChartWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Alur rekrutmen 90 hari';

    protected ?string $description = 'Tawaran dari perusahaan dan lamaran dari talenta, per tahap.';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $since = now()->subDays(90);
        $offers = RecruitmentOffer::where('created_at', '>=', $since);
        $apps = JobApplication::where('created_at', '>=', $since);

        $steps = [
            'Tawaran terkirim' => (clone $offers)->count(),
            'Talenta tertarik' => (clone $offers)->whereIn('status', ['diterima', 'tawaran_kerja', 'diambil'])->count(),
            'Lamaran masuk' => (clone $apps)->count(),
            'Lamaran ditinjau' => (clone $apps)->where('status', '!=', 'baru')->count(),
            'Tawaran kerja' => (clone $offers)->whereIn('status', ['tawaran_kerja', 'diambil', 'kedaluwarsa'])->count()
                + (clone $apps)->whereIn('status', ['tawaran_kerja', 'diambil', 'kedaluwarsa'])->count(),
            'Diambil' => (clone $offers)->where('status', 'diambil')->count() + (clone $apps)->where('status', 'diambil')->count(),
        ];

        return [
            'datasets' => [[
                'label' => 'Jumlah',
                'data' => array_values($steps),
                // Offers in blue, applications in aqua: two flows, one chart.
                'backgroundColor' => [Chart::PALETTE[1], Chart::PALETTE[1], Chart::PALETTE[2], Chart::PALETTE[2], Chart::PALETTE[3], Chart::PALETTE[3]],
                'borderRadius' => 4,
                'borderSkipped' => 'start',
            ]],
            'labels' => array_keys($steps),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'y' => ['grid' => ['display' => false]]]];
    }
}
