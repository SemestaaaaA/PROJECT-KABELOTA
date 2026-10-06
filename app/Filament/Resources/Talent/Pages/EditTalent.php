<?php

namespace App\Filament\Resources\Talent\Pages;

use App\Filament\Resources\Talent\TalentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTalent extends EditRecord
{
    protected static string $resource = TalentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
