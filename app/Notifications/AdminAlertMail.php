<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** Email copy of a bell alert, for admins who chose "Lonceng + email" for that event. */
class AdminAlertMail extends KabelotaMail
{
    public function __construct(public string $title, public string $body, public string $url) {}

    protected function message(object $notifiable): MailMessage
    {
        // Title and body carry user input: subject is plain text already, the line is markdown-escaped.
        return (new MailMessage)->subject($this->title)
            ->line(self::plain($this->body))
            ->action('Buka di admin', $this->url);
    }
}
