<?php

namespace App\Services;

use App\Models\Jadwal;
use Illuminate\Support\Carbon;

class JadwalService
{
    // Jurnal boleh diisi kapan saja pada hari jadwal tersebut (jam server)
    public static function bisaDiisi(Jadwal $jadwal, ?Carbon $now = null): bool
    {
        $now ??= now();

        return (int) $jadwal->hari === $now->dayOfWeekIso;
    }
}