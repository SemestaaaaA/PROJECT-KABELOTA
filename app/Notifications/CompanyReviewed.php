<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

class CompanyReviewed extends KabelotaMail
{
    public function __construct(public Company $company) {}

    protected function message(object $notifiable): MailMessage
    {
        return $this->company->isVerified()
            ? (new MailMessage)->subject('Perusahaan Anda sudah terverifikasi')
                ->line("{$this->company->name} sudah diverifikasi admin Kabelota.")
                ->line('Sekarang Anda bisa mengajukan rekrut, membuka CV talenta, dan memasang lowongan.')
                ->action('Cari Talenta', route('talents.index'))
            : (new MailMessage)->subject('Verifikasi perusahaan belum disetujui')
                ->line("Admin belum bisa memverifikasi {$this->company->name}.")
                ->line('Alasan: '.($this->company->rejection_reason ?? '-'))
                ->line('Perbaiki data atau dokumen, lalu simpan profil untuk mengajukan ulang.')
                ->action('Perbaiki Profil Perusahaan', route('company.profile'));
    }
}
