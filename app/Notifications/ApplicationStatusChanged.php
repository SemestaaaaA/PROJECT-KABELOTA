<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationStatusChanged extends KabelotaMail
{
    public function __construct(public JobApplication $application) {}

    protected function message(object $notifiable): MailMessage
    {
        $job = $this->application->jobPosting;
        $line = [
            'ditinjau' => "{$job->company->name} sedang meninjau lamaran Anda untuk **{$job->title}**.",
            'diterima' => "Lamaran Anda untuk **{$job->title}** diterima {$job->company->name}. Perusahaan sekarang bisa melihat nomor HP dan email Anda dan akan menghubungi Anda.",
            'ditolak' => "{$job->company->name} memutuskan tidak melanjutkan lamaran Anda untuk **{$job->title}**. Masih ada lowongan lain yang bisa Anda coba.",
        ][$this->application->status] ?? "Status lamaran Anda untuk {$job->title} berubah.";

        return (new MailMessage)
            ->subject("Lamaran {$job->title}: ".$this->application->statusLabel())
            ->line($line)
            ->action('Lihat Lamaran Saya', route('talent.applications'));
    }
}
