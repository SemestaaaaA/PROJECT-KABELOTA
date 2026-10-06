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
