<?php

namespace App\Notifications;

use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class OfferReceived extends KabelotaMail
{
    public function __construct(public RecruitmentOffer $offer) {}

    protected function message(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tawaran kerja dari {$this->offer->company_name}")
            ->line("{$this->offer->company_name} menawarkan posisi **{$this->offer->position}** kepada Anda.")
            ->line('Nomor HP dan email Anda tetap tersembunyi sampai Anda menekan Terima.')
            ->action('Lihat dan Jawab Tawaran', route('talent.offers'));
    }
}
