<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline numbers: last 30 days against the 30 days before, with an 8-week sparkline each. */
class KabelotaStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Ringkasan 30 hari terakhir';

    protected ?string $description = 'Persentase dibanding 30 hari sebelumnya. Garis kecil = jumlah per minggu, 8 minggu terakhir.';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    // Rendered with the page: these are the first things the admin needs to see.
    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $now = CarbonImmutable::now();
        $from = $now->subDays(30);
        $prev = $now->subDays(60);
        $between = fn ($q, $a, $b, $col = 'created_at') => (clone $q)->where($col, '>=', $a)->where($col, '<', $b)->count();

        $talents = Talent::query();
        $newTalents = $between($talents, $from, $now);

        $companies = Company::where('status', 'terverifikasi');
        $newCompanies = $between(Company::query(), $from, $now, 'verified_at');

        $paid = JobPosting::whereNotNull('approved_at');
        $revenue = (clone $paid)->where('approved_at', '>=', $from)->get()->sum(fn ($j) => $j->packagePrice());
        $revenueBefore = (clone $paid)->where('approved_at', '>=', $prev)->where('approved_at', '<', $from)->get()->sum(fn ($j) => $j->packagePrice());
        $revenueWeeks = Chart::weeks(8)->map(fn ($w) => (clone $paid)->where('approved_at', '>=', $w)->where('approved_at', '<', $w->addWeek())->get()->sum(fn ($j) => $j->packagePrice()))->all();

        $open = JobPosting::open()->count();
        $closingSoon = JobPosting::open()->whereDate('closes_at', '<=', today()->addDays(7))->count();

        $offers = RecruitmentOffer::query();
        $offersNow = $between($offers, $from, $now);
        $answered = RecruitmentOffer::where('created_at', '>=', $from)->where('status', '!=', 'menunggu')->count();
        $accepted = RecruitmentOffer::where('created_at', '>=', $from)->where('status', 'diterima')->count();

        $apps = JobApplication::query();
        $appsNow = $between($apps, $from, $now);
        $reviewed = JobApplication::where('created_at', '>=', $from)->where('status', '!=', 'baru')->count();

        return [
            $this->trend(Stat::make('Talenta terdaftar', number_format(Talent::count(), 0, ',', '.')), $newTalents, $between($talents, $prev, $from), 'baru')
                ->chart(Chart::perWeek(Talent::query()))
                ->icon(Heroicon::OutlinedUserGroup),
            $this->trend(Stat::make('Perusahaan terverifikasi', $companies->count()), $newCompanies, $between(Company::query(), $prev, $from, 'verified_at'), 'diverifikasi')
                ->chart(Chart::perWeek(Company::whereNotNull('verified_at'), column: 'verified_at'))
                ->icon(Heroicon::OutlinedBuildingOffice2),
            Stat::make('Lowongan tayang', $open)
                ->description($closingSoon ? "$closingSoon berakhir dalam 7 hari" : 'Tidak ada yang segera berakhir')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($closingSoon ? 'warning' : 'gray')
                ->chart(Chart::perWeek(JobPosting::whereNotNull('approved_at'), column: 'approved_at'))
                ->icon(Heroicon::OutlinedBriefcase),
            $this->trend(Stat::make('Pendapatan lowongan', Chart::rupiah($revenue)), $revenue, $revenueBefore, null)
                ->chart($revenueWeeks)
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Tawaran rekrut', $offersNow)
                ->description($offersNow ? round($accepted / $offersNow * 100).'% diterima · '.($offersNow - $answered).' belum dijawab' : 'Belum ada tawaran')
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->color($offersNow && $accepted ? 'success' : 'gray')
                ->chart(Chart::perWeek(RecruitmentOffer::query()))
                ->icon(Heroicon::OutlinedHandRaised),
            Stat::make('Lamaran masuk', $appsNow)
                ->description($appsNow ? round($reviewed / $appsNow * 100).'% sudah ditinjau perusahaan' : 'Belum ada lamaran')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($appsNow && $reviewed < $appsNow / 2 ? 'warning' : 'gray')
                ->chart(Chart::perWeek(JobApplication::query()))
                ->icon(Heroicon::OutlinedDocumentText),
        ];
    }

    /** "+12 baru · ▲ 20%" style description, green when growing, red when shrinking. */
    private function trend(Stat $stat, int|float $now, int|float $before, ?string $noun): Stat
    {
        $change = Chart::change($now, $before);
        $prefix = $noun ? '+'.number_format($now, 0, ',', '.')." $noun" : null;
        $text = collect([$prefix, $change === null ? 'belum ada pembanding' : ($change >= 0 ? "naik $change%" : 'turun '.abs($change).'%')])->filter()->implode(' · ');

        return $stat->description($text)
            ->descriptionIcon($change === null ? null : ($change >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown))
            ->color($change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger'));
    }
}
