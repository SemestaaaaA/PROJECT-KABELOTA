<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationReceived extends KabelotaMail
{
    public function __construct(public JobApplication $application) {}

    protected function message(object $notifiable): MailMessage
    {
        $job = $this->application->jobPosting;

        return (new MailMessage)
            ->subject("Pelamar baru: {$job->title}")
            ->line(\Illuminate\Support\Str::before($this->application->talent->name, ',').' ('.$this->application->talent->headline.') melamar lowongan **'.$job->title.'**.')
            ->action('Lihat Pelamar', route('company.applicants', $job));
    }
}
