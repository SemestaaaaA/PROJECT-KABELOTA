<?php

namespace App\Filament\Widgets;

use App\Models\Talent;
use Filament\Widgets\ChartWidget;

class ConcentrationChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Talenta per konsentrasi';

    protected ?string $maxHeight = '340px';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        $alumni = Talent::where('type', 'alumni')->count();

        return $alumni.' alumni · '.Talent::where('type', 'mahasiswa')->count().' mahasiswa';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $names = config('kabelota.concentrations');
        $counts = Talent::query()->selectRaw('concentration, count(*) as total')->groupBy('concentration')->pluck('total', 'concentration');

        return [
            'datasets' => [[
                'data' => collect($names)->map(fn ($n) => (int) ($counts[$n] ?? 0))->all(),
                'backgroundColor' => array_slice(Chart::PALETTE, 0, count($names)),
                'borderWidth' => 2,
                'hoverOffset' => 6,
            ]],
            // Legend labels carry the count so the split is readable without matching colors.
            'labels' => collect($names)->map(fn ($n) => $n.' ('.(int) ($counts[$n] ?? 0).')')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['cutout' => '62%', 'plugins' => ['legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 10, 'padding' => 8]]], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
