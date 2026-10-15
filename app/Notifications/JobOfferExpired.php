<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class JobOfferExpired extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $name = Str::before($r->talent->name, ',');

        return (new MailMessage)
            ->subject("Tawaran kerja untuk {$name} kedaluwarsa")
            ->line("{$name} tidak menjawab tawaran kerja **{$r->job_offer_position}** dalam 7 hari.")
            ->line('Anda bisa mengirim tawaran kerja lagi dari halaman yang sama kalau masih berminat.')
            ->action('Lihat Detail', $r->companyInboxUrl());
    }
}
