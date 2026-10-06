<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'pesan';

    protected static ?string $pluralModelLabel = 'Pesan Kontak';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->description(fn (ContactMessage $r) => $r->email)->searchable(),
                TextColumn::make('topic')->label('Topik')->badge(),
                TextColumn::make('message')->label('Pesan')->limit(80)->wrap(),
                TextColumn::make('created_at')->label('Masuk')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('topic')->label('Topik')->options(array_combine(\App\Http\Controllers\ContactController::TOPICS, \App\Http\Controllers\ContactController::TOPICS))])
            ->recordActions([
                Action::make('balas')->label('Balas email')->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.$r->topic.' (Kabelota)')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
