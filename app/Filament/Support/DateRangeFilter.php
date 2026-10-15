<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** "Dari" / "Sampai" date filter on one column, with an active-filter chip per filled date. */
class DateRangeFilter
{
    public static function make(string $column, string $label): Filter
    {
        return Filter::make($column)->label($label)
            ->schema([
                DatePicker::make('from')->label("$label dari"),
                DatePicker::make('until')->label("$label sampai"),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '<=', $date)))
            ->indicateUsing(function (array $data) use ($label): array {
                $chips = [];
                foreach (['from' => 'dari', 'until' => 'sampai'] as $field => $word) {
                    if ($data[$field] ?? null) {
                        $chips[] = Indicator::make("$label $word ".Carbon::parse($data[$field])->translatedFormat('j M Y'))->removeField($field);
                    }
                }

                return $chips;
            });
    }
}
