<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared helpers for the dashboard: one categorical palette and DB-agnostic period buckets.
 *
 * Palette validated (light #fff and dark #18181b surfaces, CVD-safe adjacent pairs) in this fixed
 * order: brand yellow, blue, aqua, violet, magenta. Assign by entity, never by rank.
 */
final class Chart
{
    public const PALETTE = ['#c98500', '#3987e5', '#199e70', '#9085e9', '#d55181'];

    public const NEUTRAL = '#a1a1aa';

    /** @return Collection<int, CarbonImmutable> start of each of the last $n weeks, oldest first */
    public static function weeks(int $n): Collection
    {
        $start = CarbonImmutable::now()->startOfWeek();

        return collect(range($n - 1, 0))->map(fn ($i) => $start->subWeeks($i));
    }

    /** @return Collection<int, CarbonImmutable> start of each of the last $n months, oldest first */
    public static function months(int $n): Collection
    {
        $start = CarbonImmutable::now()->startOfMonth();

        return collect(range($n - 1, 0))->map(fn ($i) => $start->subMonths($i));
    }

    /**
     * Rows per week for the last $n weeks. Grouping happens in PHP so it works the same on SQLite and MySQL.
     *
     * @return array<int, int>
     */
    public static function perWeek(Builder $query, int $n = 8, string $column = 'created_at'): array
    {
        $weeks = self::weeks($n);
        $dates = (clone $query)->where($column, '>=', $weeks->first())->pluck($column);

        return $weeks->map(fn (CarbonImmutable $w) => $dates->filter(fn ($d) => $d >= $w && $d < $w->addWeek())->count())->all();
    }

    public static function rupiah(int|float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    /** Percent change, or null when there is nothing to compare against. */
    public static function change(int|float $now, int|float $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }
}
