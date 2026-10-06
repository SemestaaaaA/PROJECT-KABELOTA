<?php

namespace App\Filament\Widgets;

use App\Models\JobPosting;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Pendapatan per bulan';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    public function getDescription(): ?string
    {
        $year = JobPosting::whereNotNull('approved_at')->whereYear('approved_at', now()->year)->get()->sum(fn ($j) => $j->packagePrice());

        return '6 bulan terakhir, per paket, dari lowongan yang sudah disetujui admin. Total tahun ini '.Chart::rupiah($year).'.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = Chart::months(6);
        $jobs = JobPosting::whereNotNull('approved_at')->where('approved_at', '>=', $months->first())->get(['package', 'approved_at']);

        $datasets = collect(config('kabelota.packages'))->keys()->values()->map(fn ($key, $i) => [
            'label' => config("kabelota.packages.$key.label"),
            'data' => $months->map(fn ($m) => $jobs->where('package', $key)
                ->filter(fn ($j) => $j->approved_at >= $m && $j->approved_at < $m->addMonth())->count() * config("kabelota.packages.$key.price"))->all(),
            'backgroundColor' => Chart::PALETTE[$i],
            'borderRadius' => 4,
            'borderSkipped' => 'bottom',
            'borderWidth' => 0,
        ])->all();

        return ['datasets' => $datasets, 'labels' => $months->map(fn ($m) => $m->translatedFormat('M Y'))->all()];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: { callbacks: { label: (c) => `${c.dataset.label}: Rp${c.parsed.y.toLocaleString('id-ID')}` } },
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => 'Rp' + (v / 1000).toLocaleString('id-ID') + 'rb' } },
            },
        }
        JS);
    }
}
