<?php

namespace App\Filament\Widgets;

use App\Models\Certification;
use App\Models\Talent;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class JenjangChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Jenjang SKK tertinggi';

    protected ?string $maxHeight = '340px';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        $withSkk = Talent::whereHas('certifications')->count();
        $verified = Talent::whereHas('certifications')->whereNotNull('skk_verified_at')->count();
        $expired = Certification::whereDate('expires_at', '<', today())->count();

        return $withSkk.' alumni ber-SKK · '.($withSkk ? round($verified / $withSkk * 100) : 0).'% terverifikasi · '.$expired.' SKK kedaluwarsa';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // Highest level per talent, so each person counts once.
        $top = Certification::query()->selectRaw('talent_id, max(jenjang) as top')->groupBy('talent_id')->pluck('top');
        $levels = config('kabelota.jenjang');

        return [
            'datasets' => [[
                'label' => 'Talenta',
                'data' => collect($levels)->keys()->map(fn ($l) => $top->filter(fn ($t) => (int) $t === (int) $l)->count())->values()->all(),
                'backgroundColor' => Chart::PALETTE[0],
                'borderRadius' => 4,
                'borderSkipped' => 'bottom',
            ]],
            'labels' => collect($levels)->map(fn ($name, $l) => $l.' · '.$name)->values()->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        // Ticks show only the level number; the tooltip shows "7 · Ahli Muda".
        return RawJs::make(<<<'JS'
        {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false }, title: { display: true, text: 'Jenjang KKNI' },
                     ticks: { maxRotation: 0, callback: function (v) { return this.getLabelForValue(v).split(' · ')[0] } } },
            },
        }
        JS);
    }
}
