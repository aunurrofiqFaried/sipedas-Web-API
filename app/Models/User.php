<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_GURU = 'guru';
    public const ROLE_GURU_BK = 'guru_bk';
    public const ROLE_KESISWAAN = 'kesiswaan';
    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';
    public const ROLE_STAF = 'staf';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_GURU_BK,
        self::ROLE_KESISWAAN,
        self::ROLE_KEPALA_SEKOLAH,
    ];

    public const ROLE_LABELS = [
    self::ROLE_ADMIN => 'Admin',
    self::ROLE_GURU => 'Guru',
    self::ROLE_GURU_BK => 'Guru BK',
    self::ROLE_KESISWAAN => 'Waka Kesiswaan',
    self::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
    self::ROLE_STAF => 'Staf',
    ];

    protected $fillable = [
        'name', 'username', 'nip', 'email', 'password', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_id');
    }

    // Wali kelas = guru yang menjadi wali di kelas pada tahun ajaran aktif
    public function isWaliKelas(): bool
    {
        return $this->kelasWali()
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    public function canAccessPanel(Panel $panel): bool
{
    return $this->is_active && in_array($this->role, [
        self::ROLE_ADMIN,
        self::ROLE_KESISWAAN,
        self::ROLE_KEPALA_SEKOLAH,
        self::ROLE_GURU_BK,
    ], true);
}
}
