<?php

namespace App\Filament\Resources\Talent\Pages;

use App\Filament\Resources\Talent\TalentResource;
use App\Filament\Resources\Talent\Widgets\TalentStats;
use Filament\Resources\Pages\ListRecords;

class ListTalent extends ListRecords
{
    protected static string $resource = TalentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [TalentStats::class];
    }
}
