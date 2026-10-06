<?php

namespace App\Filament\Widgets;

use App\Models\Talent;
use Filament\Widgets\ChartWidget;

class LocationChart extends ChartWidget
{
    protected static ?int $sort = 9;

    protected ?string $heading = 'Domisili talenta';

    protected ?string $description = '6 terbanyak, sisanya digabung.';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $counts = Talent::query()->selectRaw('city, count(*) as total')->groupBy('city')->orderByDesc('total')->pluck('total', 'city');
        $top = $counts->take(6);
        if ($counts->count() > 6) {
            $top['Lainnya'] = $counts->slice(6)->sum();
        }

        return [
            'datasets' => [[
                'label' => 'Talenta',
                'data' => $top->values()->map(fn ($v) => (int) $v)->all(),
                'backgroundColor' => $top->keys()->map(fn ($k) => $k === 'Lainnya' ? Chart::NEUTRAL : Chart::PALETTE[0])->all(),
                'borderRadius' => 4,
                'borderSkipped' => 'start',
            ]],
            'labels' => $top->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'y' => ['grid' => ['display' => false]]]];
    }
}
