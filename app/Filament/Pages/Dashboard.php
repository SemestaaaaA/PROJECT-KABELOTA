<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Ringkasan';

    /** Most important first: these sit in the bento. */
    private const BENTO = [
        Widgets\ActionQueue::class,
        Widgets\AdminNotificationsWidget::class,
        Widgets\InboxWidget::class,
        Widgets\KabelotaStats::class,
        Widgets\RecruitmentFunnelChart::class,
        Widgets\RegistrationsChart::class,
        Widgets\RevenueChart::class,
        Widgets\ExpiringJobsTable::class,
    ];

    /** Supporting analytics, folded by default; lazy widgets load when the section is opened. */
    private const MORE = [
        Widgets\TopCompaniesTable::class,
        Widgets\ConcentrationChart::class,
        Widgets\JenjangChart::class,
        Widgets\LocationChart::class,
        Widgets\DataQualityStats::class,
        Widgets\VisitorsWidget::class,
    ];

    // 4-column grid on wide screens: KPI rows span all, charts take 2, small cards 1.
    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 4];
    }

    public function content(Schema $schema): Schema
    {
        $grid = fn (array $widgets): Grid => Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($widgets));

        return $schema->components([
            $grid(self::BENTO)->extraAttributes(['class' => 'kb-bento']),
            Section::make('Analitik lainnya')
                ->description('Perusahaan teraktif, konsentrasi, jenjang, domisili, kualitas data, dan pengunjung.')
                ->icon('heroicon-o-chart-bar-square')
                ->collapsible()
                ->collapsed()
                ->schema([$grid(self::MORE)]),
        ]);
    }
}
