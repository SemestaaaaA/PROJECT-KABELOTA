<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class JobOfferReminder extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;

        return (new MailMessage)
            ->subject("Tawaran kerja {$r->job_offer_position} berakhir {$r->job_offer_expires_at->translatedFormat('j F')}")
            ->line("Tawaran kerja dari {$r->companyName()} untuk posisi **{$r->job_offer_position}** belum Anda jawab.")
            ->line('Kalau tidak dijawab sampai '.$r->job_offer_expires_at->translatedFormat('j F Y').', tawaran ini kedaluwarsa.')
            ->action('Jawab Sekarang', route($r->talentInboxRoute()));
    }
}
