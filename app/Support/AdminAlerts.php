<?php

namespace App\Support;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** The admin bell: one place that turns a platform event into a Filament database notification for every admin. */
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

    /** Never throws: a broken alert must not fail the user's request that triggered it. */
    public static function send(string $event, string $title, string $body, string $url): void
    {
        rescue(function () use ($event, $title, $body, $url) {
            $admins = User::where('role', 'admin')->get();
            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon(self::EVENTS[$event][1] ?? 'heroicon-o-bell-alert')
                ->iconColor($event === 'job_failed' ? 'danger' : 'warning')
                ->actions([Action::make('buka')->label('Buka')->url($url)->markAsRead()])
                ->sendToDatabase($admins);
        });
    }
}
