<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells the admin inbox about a new message from the Kontak page. */
class ContactReceived extends KabelotaMail
{
    public function __construct(public ContactMessage $contact) {}

    protected function message(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('[Kabelota] Pesan baru: '.$this->contact->topic)
            ->replyTo($this->contact->email, $this->contact->name)
            ->line("Dari {$this->contact->name} ({$this->contact->email}).")
            ->line($this->contact->message)
            ->line('Balas langsung email ini untuk menjawab pengirim.')
            ->action('Buka di Panel Admin', url('/admin/contact-messages'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The base greeting uses $notifiable->name, which an on-demand route does not have.
        return $this->message($notifiable)->greeting('Halo, Admin.')->salutation('Kabelota');
    }
}
