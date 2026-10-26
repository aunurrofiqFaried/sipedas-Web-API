<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPiket extends Model
{
    protected $table = 'jadwal_piket';

    protected $fillable = ['tahun_ajaran_id', 'user_id', 'hari', 'jam_ke_mulai', 'jam_ke_selesai', 'tugas'];

    // "Jam 1-3 (07:00-09:15)"
    public function labelJam(): string
    {
        if (! $this->jam_ke_mulai) {
            return '-';
        }

        $mulai = (int) $this->jam_ke_mulai;
        $selesai = (int) $this->jam_ke_selesai;

        $jam = JamPelajaran::semua();
        $m = $jam->get($mulai);
        $s = $jam->get($selesai);

        $rentang = $mulai === $selesai ? "Jam {$mulai}" : "Jam {$mulai}-{$selesai}";
        $waktu = ($m && $s) ? ' (' . substr($m->jam_mulai, 0, 5) . '-' . substr($s->jam_selesai, 0, 5) . ')' : '';

        return $rentang . $waktu;
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}