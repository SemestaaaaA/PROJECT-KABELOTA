<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Plain values only: the closed processes can be a mix of offers and applications, which cannot be queued as one collection. */
class ProcessClosed extends KabelotaMail
{
    /** @param list<string> $positions */
    public function __construct(public string $talentName, public array $positions, public ?string $farewell) {}

    protected function message(object $notifiable): MailMessage
    {
        $name = Str::before($this->talentName, ',');

        return (new MailMessage)
            ->subject("{$name} sudah mengambil tawaran kerja lain")
            ->line("{$name} sudah menerima tawaran kerja dari perusahaan lain, jadi proses berikut ditutup otomatis: **".implode(', ', $this->positions).'**.')
            ->lineIf(filled($this->farewell), 'Pesan dari talenta: "'.self::plain($this->farewell).'"')
            ->line('Riwayatnya tetap tersimpan di dashboard Anda.')
            ->action('Cari Talenta Lain', route('talents.index'));
    }
}
