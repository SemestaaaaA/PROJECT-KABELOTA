<?php

namespace App\Filament\Resources\AdminActivities;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Support\DateRangeFilter;
use App\Models\AdminActivity;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Read-only audit trail: who verified or rejected what, and when. */
class AdminActivityResource extends Resource
{
    protected static ?string $model = AdminActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'aktivitas';

    protected static ?string $pluralModelLabel = 'Riwayat aktivitas';

    protected static ?string $navigationLabel = 'Riwayat aktivitas';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->since()->dateTimeTooltip('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Admin')->placeholder('Akun terhapus'),
                TextColumn::make('action')->label('Aksi')->badge()
                    ->formatStateUsing(fn (string $state) => AdminActivity::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state) => str_ends_with($state, '_rejected') ? 'danger' : 'success'),
                TextColumn::make('description')->label('Keterangan')->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Admin')
                    ->options(fn () => User::where('role', 'admin')->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('action')->label('Aksi')->options(AdminActivity::ACTIONS),
                DateRangeFilter::make('created_at', 'Tanggal'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdminActivities::route('/')];
    }
}
