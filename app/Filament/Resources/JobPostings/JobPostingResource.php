<?php

namespace App\Filament\Resources\JobPostings;

use App\Filament\Resources\JobPostings\Pages\CreateJobPosting;
use App\Filament\Resources\JobPostings\Pages\EditJobPosting;
use App\Filament\Resources\JobPostings\Pages\ListJobPostings;
use App\Models\JobPosting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobPostingResource extends Resource
{
    protected static ?string $model = JobPosting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $modelLabel = 'lowongan';

    protected static ?string $pluralModelLabel = 'Lowongan';

    protected static ?int $navigationSort = 2;

    public const STATUSES = ['menunggu_verifikasi' => 'Menunggu pembayaran dicek', 'aktif' => 'Tayang', 'ditolak' => 'Ditolak'];

    public static function getNavigationBadge(): ?string
    {
        $n = JobPosting::where('status', 'menunggu_verifikasi')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
            Select::make('company_id')->label('Perusahaan')->relationship('company', 'name')->required(),
            Select::make('package')->label('Paket')->options(collect(config('kabelota.packages'))->map(fn ($p) => $p['label']))->required(),
            Select::make('status')->options(self::STATUSES)->required(),
            Select::make('location')->label('Lokasi')->options(array_combine(config('kabelota.locations'), config('kabelota.locations')))->required(),
            TextInput::make('duration_months')->label('Durasi (bulan)')->numeric()->required(),
            DatePicker::make('closes_at')->label('Tutup')->required(),
            Textarea::make('description')->label('Deskripsi')->rows(5)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->description(fn (JobPosting $r) => $r->company?->name),
                TextColumn::make('package')->label('Paket')->badge()
                    ->formatStateUsing(fn ($state) => config("kabelota.packages.$state.label"))
                    ->color(fn ($state) => $state === 'tenaga_ahli' ? 'warning' : 'gray'),
                TextColumn::make('price')->label('Bayar')->state(fn (JobPosting $r) => 'Rp'.number_format($r->packagePrice(), 0, ',', '.')),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['menunggu_verifikasi' => 'warning', 'aktif' => 'success', 'ditolak' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('closes_at')->label('Tutup')->date('j M Y')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(self::STATUSES)])
            ->recordActions([
                Action::make('bukti')->label('Bukti transfer')->icon(Heroicon::OutlinedBanknotes)->color('gray')
                    ->url(fn (JobPosting $r) => route('admin.file', ['bukti-transfer', $r->id]), shouldOpenInNewTab: true)
                    ->visible(fn (JobPosting $r) => (bool) $r->payment_proof_path),
                Action::make('setujui')->label('Setujui')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->requiresConfirmation()->modalDescription('Pastikan dana sudah masuk ke rekening. Lowongan langsung tayang.')
                    ->visible(fn (JobPosting $r) => $r->status === 'menunggu_verifikasi')
                    ->action(function (JobPosting $r) {
                        $r->update(['status' => 'aktif', 'approved_at' => now(), 'closes_at' => today()->addDays(config("kabelota.packages.{$r->package}.days"))]);
                        $r->company?->user?->notify(new \App\Notifications\JobPostingApproved($r));
                        Notification::make()->title('Lowongan tayang')->success()->send();
                    }),
                Action::make('tolak')->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->requiresConfirmation()->modalDescription('Gunakan kalau dana tidak masuk atau bukti tidak valid.')
                    ->visible(fn (JobPosting $r) => $r->status === 'menunggu_verifikasi')
                    ->action(fn (JobPosting $r) => $r->update(['status' => 'ditolak'])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobPostings::route('/'),
            'create' => CreateJobPosting::route('/create'),
            'edit' => EditJobPosting::route('/{record}/edit'),
        ];
    }
}
