<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Who actually uses Kabelota: verified companies ranked by activity in the last 90 days. */
class TopCompaniesTable extends TableWidget
{
    protected static ?int $sort = 8;

    protected static ?string $heading = 'Perusahaan teraktif (90 hari)';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    public function table(Table $table): Table
    {
        $since = now()->subDays(90);

        return $table
            ->query(Company::query()->where('status', 'terverifikasi')->withCount([
                'offers' => fn (Builder $q) => $q->where('created_at', '>=', $since),
                'offers as accepted_count' => fn (Builder $q) => $q->where('created_at', '>=', $since)->where('status', 'diterima'),
                'jobPostings' => fn (Builder $q) => $q->whereNotNull('approved_at')->where('approved_at', '>=', $since),
            ]))
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('offers_count')->orderByDesc('job_postings_count'))
            ->paginated([5])
            ->emptyStateHeading('Belum ada perusahaan terverifikasi')
            ->recordUrl(fn (Company $r) => CompanyResource::getUrl('edit', ['record' => $r]))
            ->columns([
                TextColumn::make('name')->label('Perusahaan')->wrap()->description(fn (Company $r) => $r->city),
                TextColumn::make('offers_count')->label('Tawaran')->numeric()->alignEnd()
                    ->description(fn (Company $r) => $r->offers_count ? $r->accepted_count.' diterima' : null, position: 'below'),
                TextColumn::make('job_postings_count')->label('Lowongan')->numeric()->alignEnd(),
            ]);
    }
}
