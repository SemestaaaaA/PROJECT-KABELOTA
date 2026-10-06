<?php

namespace App\Notifications;

use App\Models\Certification;
use Illuminate\Notifications\Messages\MailMessage;

class SkkExpiring extends KabelotaMail
{
    public function __construct(public Certification $certification) {}

    protected function message(object $notifiable): MailMessage
    {
        $c = $this->certification;

        return (new MailMessage)->subject('SKK Anda habis masa berlakunya dalam 30 hari')
            ->line("SKK {$c->jabatan_kerja} jenjang {$c->jenjang} (No. Reg {$c->registration_number}) berlaku sampai {$c->expires_at->translatedFormat('j F Y')}.")
            ->line('Perusahaan biasanya hanya menerima SKK yang masih berlaku untuk dokumen tender. Perpanjang lewat LSP, lalu perbarui masa berlaku dan scan SKK di profil Kabelota.')
            ->action('Perbarui Profil', route('profile.edit'));
    }
}
