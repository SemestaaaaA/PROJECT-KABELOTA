<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** Five latest bell notifications for the signed-in admin, on the dashboard bento. */
class AdminNotificationsWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-notifications';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    // Placed explicitly by the Dashboard; rendered with the page because it is near the top.
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    /** Opens one of the admin's own notifications: marks it read, then follows its "Buka" link. */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        if (! $notification) {
            return;
        }

        $notification->markAsRead();
        $url = collect($notification->data['actions'] ?? [])->first()['url'] ?? null;

        $url ? $this->redirect($url) : $this->dispatch('databaseNotificationsSent');
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        // The topbar bell (Filament's DatabaseNotifications component) re-renders on this event.
        $this->dispatch('databaseNotificationsSent');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'items' => $user->notifications()->latest()->limit(5)->get(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }
}
