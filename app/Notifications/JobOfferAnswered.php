<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class JobOfferAnswered extends KabelotaMail
{
    public function __construct(public RecruitmentOffer|JobApplication $record) {}

    protected function message(object $notifiable): MailMessage
    {
        $r = $this->record;
        $name = Str::before($r->talent->name, ',');

        if ($r->status === 'diambil') {
            return (new MailMessage)
                ->subject("{$name} mengambil tawaran kerja Anda")
                ->line("{$name} menerima tawaran kerja untuk posisi **{$r->job_offer_position}**.")
                ->line('Hubungi talenta untuk langkah berikutnya. Nomor HP dan emailnya ada di Kabelota.')
                ->action('Lihat Detail', $r->companyInboxUrl());
        }

        return (new MailMessage)
            ->subject("{$name} menolak tawaran kerja Anda")
            ->line("{$name} menolak tawaran kerja untuk posisi **{$r->job_offer_position}**.")
            ->lineIf(filled($r->closed_note), 'Alasan: "'.self::plain($r->closed_note).'"')
            ->action('Cari Talenta Lain', route('talents.index'));
    }
}
