<?php

namespace App\Filament\Resources\Talent;

use App\Enums\Availability;
use App\Filament\Resources\Talent\Pages\EditTalent;
use App\Filament\Resources\Talent\Pages\ListTalent;
use App\Models\Talent;
use App\Notifications\SkkReviewed;
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
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('certifications', 'user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Talent $r) => $r->headline),
                TextColumn::make('type')->label('Status')->badge()->formatStateUsing(fn ($state) => $state === 'alumni' ? 'Alumni' : 'Mahasiswa')
                    ->color(fn ($state) => $state === 'alumni' ? 'gray' : 'warning'),
                TextColumn::make('city')->label('Domisili'),
                TextColumn::make('availability')->label('Ketersediaan')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('skk')->label('SKK')->badge()
                    ->state(fn (Talent $r) => match (true) {
                        $r->isSkkVerified() => 'Terverifikasi',
                        (bool) $r->skk_review_note => 'Ditolak',
                        (bool) $r->skk_scan_path => 'Perlu dicek',
                        default => 'Belum ada',
                    })
                    ->color(fn ($state) => ['Terverifikasi' => 'success', 'Ditolak' => 'danger', 'Perlu dicek' => 'warning'][$state] ?? 'gray'),
                TextColumn::make('hmts_status')->label('HMTS')->formatStateUsing(fn ($state) => config("kabelota.hmts_statuses.$state"))->toggleable(),
                TextColumn::make('created_at')->label('Daftar')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Status')->options(['alumni' => 'Alumni', 'mahasiswa' => 'Mahasiswa']),
                SelectFilter::make('hmts_status')->label('HMTS')->options(config('kabelota.hmts_statuses')),
                Filter::make('skk_queue')->label('SKK perlu dicek')
                    ->query(fn (Builder $query) => $query->whereNotNull('skk_scan_path')->whereNull('skk_verified_at')->whereNull('skk_review_note')),
            ])
            ->recordActions([
                Action::make('profil')->label('Lihat profil')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (Talent $r) => route('talents.show', $r), shouldOpenInNewTab: true),
                Action::make('scan_skk')->label('Scan SKK')->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->url(fn (Talent $r) => route('admin.file', ['skk', $r->id]), shouldOpenInNewTab: true)
                    ->visible(fn (Talent $r) => (bool) $r->skk_scan_path),
                Action::make('verifikasi_skk')->label('Verifikasi SKK')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Talent $r) => 'Pastikan scan cocok dengan: '.$r->certifications->map(fn ($c) => "{$c->jabatan_kerja} jenjang {$c->jenjang} ({$c->registration_number})")->implode('; ').'.')
                    ->visible(fn (Talent $r) => $r->skk_scan_path && $r->certifications->isNotEmpty() && ! $r->isSkkVerified())
                    ->action(function (Talent $r) {
                        $r->update(['skk_verified_at' => now(), 'skk_review_note' => null]);
                        $r->user?->notify(new SkkReviewed($r));
                        Notification::make()->title("SKK {$r->name} terverifikasi")->success()->send();
                    }),
                Action::make('tolak_skk')->label('Tolak SKK')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Talent $r) => $r->skk_scan_path && ! $r->skk_review_note)
                    ->schema([Textarea::make('note')->label('Catatan (dikirim ke talenta)')->required()->default('Scan tidak terbaca atau nomor registrasi tidak sesuai dengan profil.')])
                    ->action(function (Talent $r, array $data) {
                        $r->update(['skk_verified_at' => null, 'skk_review_note' => $data['note']]);
                        $r->user?->notify(new SkkReviewed($r));
                        Notification::make()->title("SKK {$r->name} ditolak")->warning()->send();
                    }),
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
