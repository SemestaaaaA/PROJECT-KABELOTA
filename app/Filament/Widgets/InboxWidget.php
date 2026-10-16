<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Widgets\Widget;

/** Five latest contact messages, unread ones marked, on the dashboard bento. */
class InboxWidget extends Widget
{
    protected string $view = 'filament.widgets.inbox';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    // Placed explicitly by the Dashboard; rendered with the page because it is near the top.
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        // Each row deep-links to the list with the "view" modal open (Filament's ?tableAction=), which marks it read.
        return [
            'items' => ContactMessage::latest()->limit(5)->get()->each(fn (ContactMessage $m) => $m->setAttribute('open_url',
                ContactMessageResource::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $m->getKey()]))),
            'unread' => ContactMessage::unread()->count(),
            'url' => ContactMessageResource::getUrl('index'),
        ];
    }
}
