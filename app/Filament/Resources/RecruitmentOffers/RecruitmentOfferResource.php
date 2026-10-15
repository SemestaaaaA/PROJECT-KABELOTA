<?php

namespace App\Filament\Resources\RecruitmentOffers;

use App\Filament\Resources\RecruitmentOffers\Pages\ListRecruitmentOffers;
use App\Filament\Resources\RecruitmentOffers\Widgets\RecruitmentOfferStats;
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

    protected static ?int $navigationSort = 5;

    /** Short meaning of each status, in flow order. Shown as the status tooltip and in the "Apa ini?" modal. */
    public const STATUS_HELP = [
        'menunggu' => 'Talenta belum menjawab.',
        'diterima' => 'Talenta setuju dihubungi. Kontak terbuka untuk perusahaan.',
        'tawaran_kerja' => 'Perusahaan mengirim posisi dan tanggal mulai. Berlaku 7 hari.',
        'diambil' => 'Talenta menerima. Proses lain miliknya ditutup.',
        'ditolak' => 'Talenta menolak tawaran.',
        'ditutup' => 'Talenta mengambil kerja di tempat lain.',
        'kedaluwarsa' => 'Tidak dijawab dalam 7 hari.',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['talent']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('talent.name')->label('Talenta')->searchable(),
                TextColumn::make('company_name')->label('Perusahaan')->searchable(),
                TextColumn::make('position')->label('Posisi')->wrap(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => RecruitmentOffer::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['menunggu' => 'warning', 'diterima' => 'info', 'tawaran_kerja' => 'warning', 'diambil' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray')
                    ->tooltip(fn ($state) => self::STATUS_HELP[$state] ?? null),
                TextColumn::make('created_at')->label('Dikirim')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(RecruitmentOffer::STATUSES),
                SelectFilter::make('company_id')->label('Perusahaan')->relationship('company', 'name')->searchable(),
            ]);
    }

    public static function getWidgets(): array
    {
        return [RecruitmentOfferStats::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListRecruitmentOffers::route('/')];
    }
}
