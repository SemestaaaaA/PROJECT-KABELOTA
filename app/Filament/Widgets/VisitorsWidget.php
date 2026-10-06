<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** Umami's public share page embedded in the dashboard. Shown only when UMAMI_SHARE_URL is set. */
class VisitorsWidget extends Widget
{
    protected static ?int $sort = 12;

    protected string $view = 'filament.widgets.visitors';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return filled(config('kabelota.analytics.share_url'));
    }
}
