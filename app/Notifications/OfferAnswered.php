<?php

namespace App\Notifications;

use App\Models\RecruitmentOffer;
use Illuminate\Notifications\Messages\MailMessage;

class OfferAnswered extends KabelotaMail
{
    public function __construct(public RecruitmentOffer $offer) {}

    protected function message(object $notifiable): MailMessage
    {
        $name = \Illuminate\Support\Str::before($this->offer->talent->name, ',');
        $mail = (new MailMessage)->subject($this->offer->status === 'diterima'
            ? "{$name} menerima tawaran Anda"
            : "{$name} menolak tawaran Anda");

        return $this->offer->status === 'diterima'
            ? $mail->line("{$name} menerima tawaran untuk posisi **{$this->offer->position}**.")
                ->line('Nomor HP dan email talenta sekarang terlihat di halaman Tawaran Terkirim.')
                ->action('Lihat Kontak Talenta', route('company.offers'))
            : $mail->line("{$name} menolak tawaran untuk posisi **{$this->offer->position}**.")
                ->lineIf((bool) $this->offer->response_note, 'Catatan: "'.$this->offer->response_note.'"')
                ->action('Cari Talenta Lain', route('talents.index'));
    }
}
