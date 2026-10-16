<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminAlertMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * The admin bell: one place that turns a platform event into a Filament database notification for every admin,
 * plus an email copy for admins who asked for one (per-event preference on the profile page).
 */
class AdminAlerts
{
    /** Event keys (also used by per-admin preferences in 4c) and their bell icons. */
    public const EVENTS = [
        'company_verification' => ['Perusahaan menunggu verifikasi', 'heroicon-o-building-office-2'],
        'payment_proof' => ['Bukti bayar lowongan', 'heroicon-o-banknotes'],
        'skk_review' => ['SKK perlu dicek', 'heroicon-o-identification'],
        'contact_message' => ['Pesan kontak', 'heroicon-o-envelope'],
        'job_failed' => ['Job atau email gagal', 'heroicon-o-exclamation-triangle'],
    ];

    /** Per-event delivery choices an admin can pick on the profile page; 'bell' is the default. */
    public const CHANNELS = [
        'bell' => 'Lonceng',
        'bell_email' => 'Lonceng + email',
        'off' => 'Mati',
    ];

    /** Never throws: a broken alert must not fail the user's request that triggered it. Pass plain text. */
    public static function send(string $event, string $title, string $body, string $url): void
    {
        rescue(function () use ($event, $title, $body, $url) {
            $admins = User::where('role', 'admin')->get()
                ->reject(fn (User $admin) => $admin->adminAlertChannel($event) === 'off');
            if ($admins->isEmpty()) {
                return;
            }

            // Title and body carry user input (names, messages, job titles). Filament renders them as
            // sanitized HTML, which still allows links, so store them escaped: they show as plain text.
            Notification::make()
                ->title(e($title))
                ->body(e($body))
                ->icon(self::EVENTS[$event][1] ?? 'heroicon-o-bell-alert')
                ->iconColor($event === 'job_failed' ? 'danger' : 'warning')
                ->actions([Action::make('buka')->label('Buka')->url($url)->markAsRead()])
                ->sendToDatabase($admins);

            // The email copy is queued (KabelotaMail), so a slow mail server never delays the request.
            foreach ($admins->filter(fn (User $admin) => $admin->adminAlertChannel($event) === 'bell_email') as $admin) {
                $admin->notify(new AdminAlertMail($title, $body, $url));
            }
        });
    }
}
