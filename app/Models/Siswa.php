<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siswa extends Model
{
    protected $table = 'siswa';

    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    protected $fillable = [
        'nis', 'nisn', 'nama', 'jenis_kelamin', 'agama',
        'nama_wali', 'hubungan_wali', 'no_hp_wali',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function kelasSiswa(): HasMany
    {
        return $this->hasMany(KelasSiswa::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function kelasSiswaAktif(): HasOne
    {
        return $this->hasOne(KelasSiswa::class)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_active', true));
    }

    public function pelanggaran(): HasMany
    {
        return $this->hasMany(PelanggaranSiswa::class);
    }

    public function spSiswa(): HasMany
    {
        return $this->hasMany(SpSiswa::class);
    }

    // Total poin pelanggaran dalam satu tahun ajaran (poin reset per tahun ajaran)
    public function totalPoin(int $tahunAjaranId): int
    {
        return (int) $this->pelanggaran()
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->sum('poin');
    }
}
