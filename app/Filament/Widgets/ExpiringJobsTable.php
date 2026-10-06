<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\JobPosting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringJobsTable extends TableWidget
{
    protected static ?int $sort = 10;

    protected static ?string $heading = 'Lowongan berakhir dalam 14 hari';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(JobPosting::open()->with('company')->withCount('applications')->whereDate('closes_at', '<=', today()->addDays(14)))
            ->defaultSort('closes_at')
            ->paginated([5])
            ->emptyStateHeading('Tidak ada lowongan yang segera berakhir')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->recordUrl(fn (JobPosting $r) => JobPostingResource::getUrl('edit', ['record' => $r]))
            ->columns([
                TextColumn::make('title')->label('Lowongan')->wrap()->description(fn (JobPosting $r) => $r->company->name),
                TextColumn::make('package')->label('Paket')->badge()->formatStateUsing(fn ($state) => config("kabelota.packages.$state.label")),
                TextColumn::make('applications_count')->label('Pelamar')->numeric()->alignEnd()
                    ->color(fn ($state) => $state ? null : 'danger')
                    ->description(fn (JobPosting $r) => $r->applications_count ? null : 'belum ada', position: 'below'),
                TextColumn::make('closes_at')->label('Berakhir')->date('j M')->description(fn (JobPosting $r) => today()->diffInDays($r->closes_at).' hari lagi'),
            ]);
    }
}
