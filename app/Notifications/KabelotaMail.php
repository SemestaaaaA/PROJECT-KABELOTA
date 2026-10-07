<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for all Kabelota emails: queued, mail only, Indonesian footer.
 * Subclasses implement message().
 */
abstract class KabelotaMail extends Notification implements ShouldQueue
{
    use Queueable;

    // Retry when the mail server is briefly unavailable (Gmail rate limits, network blips).
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    abstract protected function message(object $notifiable): MailMessage;

    public function toMail(object $notifiable): MailMessage
    {
        return $this->message($notifiable)
            ->greeting('Halo, '.\Illuminate\Support\Str::before($notifiable->name, ',').'.')
            ->salutation('Salam, Tim Kabelota');
    }
}
