<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::unread()->count();

        return $unread ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Belum dibaca';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->description(fn (ContactMessage $r) => $r->email.($r->user_id ? ' · akun terdaftar' : ''))->searchable()
                    ->weight(fn (ContactMessage $r) => $r->read_at ? null : FontWeight::Bold),
                TextColumn::make('read_at')->label('Status')->badge()
                    ->getStateUsing(fn (ContactMessage $r) => $r->read_at ? 'Dibaca' : 'Baru')
                    ->color(fn (string $state) => $state === 'Baru' ? 'warning' : 'gray'),
                TextColumn::make('topic')->label('Topik')->badge(),
                TextColumn::make('message')->label('Pesan')->limit(80)->wrap(),
                TextColumn::make('created_at')->label('Masuk')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read_at')->label('Status baca')->nullable()->trueLabel('Sudah dibaca')->falseLabel('Belum dibaca'),
                SelectFilter::make('topic')->label('Topik')->options(array_combine(\App\Http\Controllers\ContactController::TOPICS, \App\Http\Controllers\ContactController::TOPICS)),
            ])
            ->recordActions([
                // Opening the message marks it read; the default fill is kept so the infolist has its state.
                ViewAction::make()->label('Baca')
                    ->mountUsing(function (ContactMessage $record, ?Schema $schema): void {
                        $record->markAsRead();
                        $schema?->fill($record->attributesToArray());
                    })
                    ->schema([
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('topic')->label('Topik')->badge(),
                        TextEntry::make('message')->label('Pesan')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
                        TextEntry::make('created_at')->label('Masuk')->dateTime('d M Y H:i'),
                    ]),
                Action::make('baca')->label('Tandai dibaca')->icon(Heroicon::OutlinedCheck)->color('gray')
                    ->visible(fn (ContactMessage $r) => ! $r->read_at)
                    ->action(fn (ContactMessage $r) => $r->markAsRead()),
                Action::make('balas')->label('Balas email')->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.$r->topic.' (Kabelota)')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
