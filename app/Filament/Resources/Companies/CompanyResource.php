<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    protected static ?int $navigationSort = 1;

    public const STATUSES = ['menunggu' => 'Menunggu verifikasi', 'terverifikasi' => 'Terverifikasi', 'ditolak' => 'Ditolak'];

    public static function getNavigationBadge(): ?string
    {
        $n = Company::where('status', 'menunggu')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data perusahaan')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required(),
                Select::make('type')->label('Jenis')->options(['konsultan' => 'Konsultan', 'kontraktor' => 'Kontraktor', 'pemilik_proyek' => 'Pemilik proyek', 'lainnya' => 'Lainnya'])->required(),
                TextInput::make('nib')->label('NIB'),
                Select::make('city')->label('Kota')->options(array_combine(config('kabelota.locations'), config('kabelota.locations'))),
                TextInput::make('contact_name')->label('Penanggung jawab'),
                TextInput::make('contact_phone')->label('Nomor HP'),
                TextInput::make('website')->url(),
                Textarea::make('about')->label('Profil singkat')->columnSpanFull(),
            ]),
            Section::make('Verifikasi')->columns(2)->schema([
                Select::make('status')->options(self::STATUSES)->required(),
                TextInput::make('rejection_reason')->label('Alasan penolakan'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Company $r) => $r->user?->email),
                TextColumn::make('type')->label('Jenis')->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('city')->label('Kota'),
                TextColumn::make('nib')->label('NIB')->fontFamily('mono')->placeholder('-'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['menunggu' => 'warning', 'terverifikasi' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Daftar')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
            ])
            ->recordActions([
                Action::make('dokumen')->label('NIB/SBU')->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->url(fn (Company $r) => route('admin.file', ['legalitas', $r->id]), shouldOpenInNewTab: true)
                    ->visible(fn (Company $r) => (bool) $r->legal_doc_path),
                Action::make('verifikasi')->label('Verifikasi')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->requiresConfirmation()->modalDescription('Perusahaan ini akan bisa mengajukan rekrut, membuka CV talenta, dan memasang lowongan.')
                    ->visible(fn (Company $r) => $r->status !== 'terverifikasi')
                    ->action(function (Company $r) {
                        $r->update(['status' => 'terverifikasi', 'verified_at' => now(), 'rejection_reason' => null]);
                        $r->user?->notify(new \App\Notifications\CompanyReviewed($r));
                        Notification::make()->title("{$r->name} terverifikasi")->success()->send();
                    }),
                Action::make('tolak')->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Company $r) => $r->status === 'menunggu')
                    ->schema([Textarea::make('reason')->label('Alasan (dikirim ke perusahaan)')->required()->default('NIB tidak terbaca atau tidak sesuai nama perusahaan.')])
                    ->action(function (Company $r, array $data) {
                        $r->update(['status' => 'ditolak', 'rejection_reason' => $data['reason']]);
                        $r->user?->notify(new \App\Notifications\CompanyReviewed($r));
                        Notification::make()->title("{$r->name} ditolak")->warning()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
