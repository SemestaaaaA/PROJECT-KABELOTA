<?php

namespace App\Filament\Resources\RecruitmentOffers;

use App\Filament\Resources\RecruitmentOffers\Pages\ListRecruitmentOffers;
use App\Models\RecruitmentOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Read-only log: offers belong to companies and talents, admin only monitors. */
class RecruitmentOfferResource extends Resource
{
    protected static ?string $model = RecruitmentOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $modelLabel = 'tawaran';

    protected static ?string $pluralModelLabel = 'Tawaran Rekrut';

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
                TextColumn::make('talent.name')->label('Talenta')->searchable(),
                TextColumn::make('company_name')->label('Perusahaan')->searchable(),
                TextColumn::make('position')->label('Posisi')->wrap(),
                TextColumn::make('status')->badge()->color(fn ($s) => ['menunggu' => 'warning', 'diterima' => 'success', 'ditolak' => 'danger'][$s] ?? 'gray'),
                TextColumn::make('created_at')->label('Dikirim')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(['menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRecruitmentOffers::route('/')];
    }
}
