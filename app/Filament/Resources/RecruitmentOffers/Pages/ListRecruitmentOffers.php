<?php

namespace App\Filament\Resources\RecruitmentOffers\Pages;

use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use App\Filament\Resources\RecruitmentOffers\Widgets\RecruitmentOfferStats;
use App\Models\RecruitmentOffer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentOffers extends ListRecords
{
    protected static string $resource = RecruitmentOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bantuan')->label('Apa ini?')->icon('heroicon-o-question-mark-circle')->color('gray')
                ->modalHeading('Tawaran rekrut')
                ->modalContent(view('filament.help.recruitment-offers', [
                    'statuses' => RecruitmentOffer::STATUSES,
                    'help' => RecruitmentOfferResource::STATUS_HELP,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RecruitmentOfferStats::class];
    }
}
