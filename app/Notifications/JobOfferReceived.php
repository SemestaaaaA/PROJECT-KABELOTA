<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class JobOfferReceived extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $start = $r->job_offer_start_date?->translatedFormat('j F Y');

        return (new MailMessage)
            ->subject("Tawaran kerja dari {$r->companyName()}: {$r->job_offer_position}")
            ->line("{$r->companyName()} menawarkan posisi **{$r->job_offer_position}**".($start ? ", mulai {$start}." : '.'))
            ->lineIf(filled($r->job_offer_note), 'Catatan perusahaan: "'.$r->job_offer_note.'"')
            ->line('Tawaran ini berlaku sampai '.$r->job_offer_expires_at->translatedFormat('j F Y').'. Kalau Anda mengambilnya, proses Anda dengan perusahaan lain ditutup otomatis.')
            ->action('Jawab Tawaran Kerja', route($r->talentInboxRoute()));
    }
}
