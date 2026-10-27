<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiSholat extends Model
{
    protected $table = 'absensi_sholat';

    public const WAKTU = ['dhuhur', 'ashar'];
    public const STATUS = ['hadir', 'tidak_hadir', 'berhalangan'];

    protected $fillable = ['siswa_id', 'kelas_id', 'tanggal', 'waktu', 'status', 'diinput_oleh'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function penginput(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }
}