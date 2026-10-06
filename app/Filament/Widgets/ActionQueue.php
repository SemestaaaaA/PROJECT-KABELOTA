<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Talent\TalentResource;
use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\JobPosting;
use App\Models\Talent;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** What the admin has to act on today, oldest item first. */
class ActionQueue extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Perlu tindakan';

    protected ?string $description = 'Klik kartu untuk membuka antreannya. Usia menunjukkan item yang paling lama menunggu.';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    // Rendered with the page: these are the first things the admin needs to see.
    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        return [
            $this->queue('Verifikasi perusahaan', Company::where('status', 'menunggu'), Heroicon::OutlinedBuildingOffice2,
                CompanyResource::getUrl('index', ['filters' => ['status' => ['value' => 'menunggu']]])),
            $this->queue('Bukti transfer lowongan', JobPosting::where('status', 'menunggu_verifikasi'), Heroicon::OutlinedBanknotes,
                JobPostingResource::getUrl('index', ['filters' => ['status' => ['value' => 'menunggu_verifikasi']]])),
            $this->queue('Scan SKK perlu dicek', Talent::whereNotNull('skk_scan_path')->whereNull('skk_verified_at')->whereNull('skk_review_note'), Heroicon::OutlinedCheckBadge,
                TalentResource::getUrl('index', ['filters' => ['skk_queue' => ['isActive' => true]]])),
            $this->queue('Pesan kontak 7 hari', ContactMessage::where('created_at', '>=', now()->subWeek()), Heroicon::OutlinedChatBubbleLeftRight,
                ContactMessageResource::getUrl('index'), warn: false),
        ];
    }

    private function queue(string $label, Builder $query, Heroicon $icon, string $url, bool $warn = true): Stat
    {
        $count = (clone $query)->count();
        $oldest = $count ? (clone $query)->oldest()->value('created_at') : null;

        return Stat::make($label, $count)
            ->icon($icon)
            ->description($count ? 'Tertua masuk '.\Illuminate\Support\Carbon::parse($oldest)->diffForHumans() : 'Tidak ada antrean')
            ->descriptionIcon($count ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle)
            ->color($count ? ($warn ? 'warning' : 'info') : 'success')
            ->url($url);
    }
}
