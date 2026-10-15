<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Widgets\JobApplicationStats;
use App\Models\Company;
use App\Models\JobApplication;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only: companies manage their own applicants; admin monitors. */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'lamaran';

    protected static ?string $pluralModelLabel = 'Lamaran';

    protected static ?int $navigationSort = 4;

    private const STATUS_COLORS = ['baru' => 'info', 'ditinjau' => 'warning', 'tawaran_kerja' => 'warning', 'diambil' => 'success', 'ditolak' => 'danger'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['talent', 'jobPosting.company']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('talent.name')->label('Pelamar')->searchable()->description(fn (JobApplication $r) => $r->talent?->headline),
                TextColumn::make('jobPosting.title')->label('Lowongan')->wrap()->description(fn (JobApplication $r) => $r->jobPosting?->company?->name),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => JobApplication::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('company_note')->label('Alasan tolak')->limit(40)->placeholder('-')
                    ->tooltip(fn ($state) => mb_strlen((string) $state) > 40 ? $state : null)
                    ->toggleable(),
                TextColumn::make('job_offer_expires_at')->label('Batas jawab')->date('j M Y')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Melamar')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(JobApplication::STATUSES),
                SelectFilter::make('company')->label('Perusahaan')->searchable()
                    ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('jobPosting', fn ($j) => $j->whereIn('company_id', (array) $id)),
                    )),
                SelectFilter::make('job_posting_id')->label('Lowongan')->relationship('jobPosting', 'title')->searchable(),
            ])
            ->recordActions([
                ViewAction::make('detail')->label('Detail')
                    ->modalHeading(fn (JobApplication $r) => 'Lamaran '.$r->talent?->name)
                    ->schema(self::detailSchema()),
            ]);
    }

    /** Read-only summary of one application; empty parts are left out. */
    private static function detailSchema(): array
    {
        $filled = fn (string $attribute) => fn (JobApplication $record) => filled($record->{$attribute});

        return [
            Section::make('Lamaran')->columns(2)->schema([
                TextEntry::make('talent.name')->label('Pelamar'),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state) => JobApplication::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('jobPosting.title')->label('Lowongan'),
                TextEntry::make('jobPosting.company.name')->label('Perusahaan'),
                TextEntry::make('created_at')->label('Melamar')->date('j M Y'),
                TextEntry::make('message')->label('Pesan pelamar')->columnSpanFull()->visible($filled('message')),
                TextEntry::make('company_note')->label('Catatan perusahaan')->columnSpanFull()->visible($filled('company_note')),
            ]),
            Section::make('Tawaran kerja')->columns(3)->visible($filled('job_offered_at'))->schema([
                TextEntry::make('job_offer_position')->label('Posisi'),
                TextEntry::make('job_offer_start_date')->label('Tanggal mulai')->date('j M Y'),
                TextEntry::make('job_offer_expires_at')->label('Batas jawab')->date('j M Y'),
                TextEntry::make('job_offer_note')->label('Catatan tawaran')->columnSpanFull()->visible($filled('job_offer_note')),
            ]),
            Section::make('Pesan perpisahan')->visible($filled('closed_note'))->schema([
                TextEntry::make('closed_note')->hiddenLabel(),
            ]),
        ];
    }

    public static function getWidgets(): array
    {
        return [JobApplicationStats::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListJobApplications::route('/')];
    }
}
