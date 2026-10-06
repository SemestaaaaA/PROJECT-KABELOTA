<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\RecruitmentOffer;
use App\Models\Talent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KabelotaStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pendingCompanies = Company::where('status', 'menunggu')->count();
        $pendingJobs = JobPosting::where('status', 'menunggu_verifikasi')->count();
        $revenue = JobPosting::where('status', 'aktif')->whereMonth('approved_at', now()->month)->get()->sum(fn ($j) => $j->packagePrice());
        $offers = RecruitmentOffer::where('created_at', '>=', now()->startOfMonth())->count();
        $accepted = RecruitmentOffer::where('status', 'diterima')->where('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Talenta terdaftar', Talent::count())
                ->description(Talent::where('type', 'mahasiswa')->count().' mahasiswa · '.Talent::where('created_at', '>=', now()->subDays(7))->count().' baru minggu ini'),
            Stat::make('Perlu verifikasi', $pendingCompanies + $pendingJobs)
                ->description("$pendingCompanies perusahaan · $pendingJobs bukti transfer")
                ->color($pendingCompanies + $pendingJobs ? 'warning' : 'success'),
            Stat::make('Pendapatan lowongan bulan ini', 'Rp'.number_format($revenue, 0, ',', '.'))
                ->description('Dari lowongan yang sudah disetujui'),
            Stat::make('Tawaran rekrut bulan ini', $offers)
                ->description("$accepted diterima talenta"),
        ];
    }
}
