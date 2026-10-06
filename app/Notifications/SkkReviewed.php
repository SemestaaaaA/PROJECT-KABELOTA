<?php

namespace App\Notifications;

use App\Models\Talent;
use Illuminate\Notifications\Messages\MailMessage;

class SkkReviewed extends KabelotaMail
{
    public function __construct(public Talent $talent) {}

    protected function message(object $notifiable): MailMessage
    {
        return $this->talent->isSkkVerified()
            ? (new MailMessage)->subject('SKK Anda sudah terverifikasi')
                ->line('Admin Kabelota sudah mencocokkan scan SKK dengan data di profil Anda.')
                ->line('Profil Anda sekarang memakai tanda SKK Terverifikasi dan muncul di filter "Hanya SKK terverifikasi".')
                ->action('Lihat Profil', route('talents.show', $this->talent))
            : (new MailMessage)->subject('SKK Anda belum bisa diverifikasi')
                ->line('Admin belum bisa mencocokkan scan SKK dengan data di profil Anda.')
                ->line('Catatan: '.($this->talent->skk_review_note ?? '-'))
                ->line('Perbaiki data SKK atau unggah ulang scan, lalu simpan profil. Admin akan memeriksa lagi.')
                ->action('Perbaiki Profil', route('profile.edit'));
    }
}
