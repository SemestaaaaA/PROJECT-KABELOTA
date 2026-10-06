<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent immediately (not queued) to the ops address when a queued job fails for good. */
class JobFailedAlert extends Notification
{
    public function __construct(public string $job, public string $error) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->error()
            ->subject('[Kabelota] Job gagal: '.$this->job)
            ->line('Satu proses latar (biasanya email notifikasi) gagal setelah dicoba ulang.')
            ->line('Job: '.$this->job)
            ->line('Error: '.$this->error)
            ->line('Cek storage/logs, perbaiki penyebabnya, lalu jalankan `php artisan queue:retry all`.');
    }
}
