<?php

namespace App\Filament\Widgets;

use App\Models\Certification;
use App\Models\Talent;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** How usable the talent pool is for companies: photos, CVs, verified and valid SKK. */
class DataQualityStats extends StatsOverviewWidget
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'Kualitas data talenta';

    protected ?string $description = 'Profil yang lengkap lebih sering ditawari. Angka rendah = ajak talenta melengkapi profil.';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $total = max(1, Talent::count());
        $pct = fn (int $n) => round($n / $total * 100).'%';
        $photo = Talent::whereNotNull('photo_path')->count();
        $cv = Talent::whereNotNull('cv_path')->count();
        $hidden = Talent::where('is_visible', false)->count();
        $expiring = Certification::whereBetween('expires_at', [today(), today()->addDays(60)])->count();
        $expired = Certification::whereDate('expires_at', '<', today())->count();

        return [
            Stat::make('Profil dengan foto', $pct($photo))->description("$photo dari $total talenta")->icon(Heroicon::OutlinedCamera)
                ->color($photo / $total >= .6 ? 'success' : 'warning'),
            Stat::make('Profil dengan CV', $pct($cv))->description("$cv dari $total talenta")->icon(Heroicon::OutlinedDocumentArrowUp)
                ->color($cv / $total >= .6 ? 'success' : 'warning'),
            Stat::make('SKK habis ≤ 60 hari', $expiring)->description($expired.' sudah kedaluwarsa')->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($expiring || $expired ? 'warning' : 'success'),
            Stat::make('Profil disembunyikan', $hidden)->description('Tidak muncul di Cari Talenta')->icon(Heroicon::OutlinedEyeSlash)->color('gray'),
        ];
    }
}
