<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class JamPelajaran extends Model
{
    protected $table = 'jam_pelajaran';

    protected $fillable = ['jam_ke', 'jam_mulai', 'jam_selesai'];

    protected static ?Collection $cache = null;

    // Semua jam pelajaran, diindeks menurut nomor jam ke- (dimuat sekali per permintaan)
    public static function semua(): Collection
    {
        return static::$cache ??= static::all()->keyBy('jam_ke');
    }

    protected static function booted(): void
    {
        // Jam ke-N diubah = semua jadwal tahun ajaran aktif yang memakai jam ke-N ikut diperbarui
        static::saved(function (JamPelajaran $jam) {
            $taId = TahunAjaran::aktif()?->id;

            if (! $taId) {
                return;
            }

            Jadwal::where('tahun_ajaran_id', $taId)
                ->where('jam_ke_mulai', $jam->jam_ke)
                ->update(['jam_mulai' => $jam->jam_mulai]);

            Jadwal::where('tahun_ajaran_id', $taId)
                ->where('jam_ke_selesai', $jam->jam_ke)
                ->update(['jam_selesai' => $jam->jam_selesai]);
        });
    }

    // Pilihan untuk form: [1 => "Jam 1 (07:00-07:45)", 2 => ...]
    public static function opsi(): array
    {
        return static::orderBy('jam_ke')
            ->get()
            ->mapWithKeys(fn (JamPelajaran $j) => [
                $j->jam_ke => "Jam {$j->jam_ke} (" . substr($j->jam_mulai, 0, 5) . '-' . substr($j->jam_selesai, 0, 5) . ')',
            ])
            ->all();
    }

    // Isi jam_mulai dan jam_selesai pada data jadwal dari nomor jam ke-nya
    public static function isiWaktu(array $data): array
    {
        $mulai = static::where('jam_ke', $data['jam_ke_mulai'])->first();
        $selesai = static::where('jam_ke', $data['jam_ke_selesai'])->first();

        $data['jam_mulai'] = $mulai?->jam_mulai;
        $data['jam_selesai'] = $selesai?->jam_selesai;

        return $data;
    }
}