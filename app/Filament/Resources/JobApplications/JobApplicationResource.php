<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Models\JobApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Read-only: companies manage their own applicants; admin monitors. */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'lamaran';

    protected static ?string $pluralModelLabel = 'Lamaran';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('talent.name')->label('Pelamar')->searchable()->description(fn (JobApplication $r) => $r->talent?->headline),
                TextColumn::make('jobPosting.title')->label('Lowongan')->wrap()->description(fn (JobApplication $r) => $r->jobPosting?->company?->name),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => JobApplication::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['baru' => 'info', 'ditinjau' => 'warning', 'diterima' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Melamar')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(JobApplication::STATUSES)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListJobApplications::route('/')];
    }
}
