<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Notifications\Messages\MailMessage;

class JobPostingApproved extends KabelotaMail
{
    public function __construct(public JobPosting $job) {}

    protected function message(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Lowongan tayang: {$this->job->title}")
            ->line('Pembayaran Anda sudah dicek. Lowongan **'.$this->job->title.'** tayang sampai '.$this->job->closes_at->translatedFormat('j F Y').'.')
            ->action('Lihat Lowongan Saya', route('company.jobs'));
    }
}
