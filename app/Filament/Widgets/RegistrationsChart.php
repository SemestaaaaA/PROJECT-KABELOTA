<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Talent;
use Filament\Widgets\ChartWidget;

class RegistrationsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Pendaftaran per minggu';

    protected ?string $description = '12 minggu terakhir. Perusahaan dihitung saat mendaftar, belum tentu sudah diverifikasi.';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                $this->series('Talenta', Chart::perWeek(Talent::query(), 12), Chart::PALETTE[0], []),
                // Second series is dashed so the two lines stay distinguishable without color.
                $this->series('Perusahaan', Chart::perWeek(Company::query(), 12), Chart::PALETTE[1], [6, 4]),
            ],
            'labels' => Chart::weeks(12)->map(fn ($w) => $w->translatedFormat('j M'))->all(),
        ];
    }

    private function series(string $label, array $data, string $color, array $dash): array
    {
        return [
            'label' => $label, 'data' => $data, 'borderColor' => $color, 'backgroundColor' => $color,
            'borderWidth' => 2, 'borderDash' => $dash, 'pointRadius' => 3, 'pointHoverRadius' => 6, 'tension' => 0.25, 'fill' => false,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false]]],
        ];
    }
}
