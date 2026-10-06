<?php

namespace App\Filament\Resources\Talent;

use App\Enums\Availability;
use App\Filament\Resources\Talent\Pages\EditTalent;
use App\Filament\Resources\Talent\Pages\ListTalent;
use App\Models\Talent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TalentResource extends Resource
{
    protected static ?string $model = Talent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'talenta';

    protected static ?string $pluralModelLabel = 'Talenta';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama')->required(),
            Select::make('type')->label('Status')->options(['alumni' => 'Alumni', 'mahasiswa' => 'Mahasiswa'])->required(),
            TextInput::make('headline')->label('Jabatan utama')->required(),
            Select::make('concentration')->label('Konsentrasi')->options(array_combine(config('kabelota.concentrations'), config('kabelota.concentrations'))),
            Select::make('availability')->label('Ketersediaan')->options(collect(Availability::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()])),
            Select::make('hmts_status')->label('Keanggotaan HMTS')->options(config('kabelota.hmts_statuses')),
            TextInput::make('hmts_position')->label('Jabatan HMTS'),
            Textarea::make('bio')->label('Ringkasan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Talent $r) => $r->headline),
                TextColumn::make('type')->label('Status')->badge()->formatStateUsing(fn ($s) => $s === 'alumni' ? 'Alumni' : 'Mahasiswa')
                    ->color(fn ($s) => $s === 'alumni' ? 'gray' : 'warning'),
                TextColumn::make('city')->label('Domisili'),
                TextColumn::make('availability')->label('Ketersediaan')->badge()->formatStateUsing(fn ($s) => $s->label()),
                TextColumn::make('hmts_status')->label('HMTS')->formatStateUsing(fn ($s) => config("kabelota.hmts_statuses.$s"))->toggleable(),
                TextColumn::make('created_at')->label('Daftar')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Status')->options(['alumni' => 'Alumni', 'mahasiswa' => 'Mahasiswa']),
                SelectFilter::make('hmts_status')->label('HMTS')->options(config('kabelota.hmts_statuses')),
            ])
            ->recordActions([
                Action::make('profil')->label('Lihat profil')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (Talent $r) => route('talents.show', $r), shouldOpenInNewTab: true),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTalent::route('/'),
            'edit' => EditTalent::route('/{record}/edit'),
        ];
    }
}
