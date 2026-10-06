<?php

namespace App\Enums;

enum Availability: string
{
    case Tersedia = 'tersedia';
    case TerikatKontrak = 'terikat_kontrak';
    case TidakTersedia = 'tidak_tersedia';

    public function label(): string
    {
        return match ($this) {
            self::Tersedia => 'Tersedia',
            self::TerikatKontrak => 'Terikat kontrak',
            self::TidakTersedia => 'Tidak tersedia',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Tersedia => 'b-avail',
            self::TerikatKontrak => 'b-contract',
            self::TidakTersedia => 'b-off',
        };
    }
}
