<?php

namespace App\Filament\Resources\Jadwals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use App\Models\JamPelajaran;

class JadwalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun ajaran')
                ->relationship('tahunAjaran', 'nama')
                ->default(fn () => TahunAjaran::aktif()?->id)
                ->required()
                ->live(),

            Select::make('kelas_id')
                ->label('Kelas')
                ->relationship(
                    'kelas',
                    'nama',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('tahun_ajaran_id', $get('tahun_ajaran_id')),
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('mapel_id')
                ->label('Mata pelajaran')
                ->relationship('mapel', 'nama')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('guru_id')
                ->label('Guru')
                ->relationship(
                    'guru',
                    'name',
                    modifyQueryUsing: fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereIn('role', ['guru', 'guru_bk', 'kesiswaan', 'kepala_sekolah']),
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('ruang_id')
                ->label('Ruang (lab/kelas)')
                ->relationship('ruang', 'nama')
                ->searchable()
                ->preload()
                ->placeholder('Belum diatur'),

            Select::make('hari')
                ->options(Jadwal::HARI)
                ->required(),

            Select::make('jam_ke_mulai')
                ->label('Mulai jam ke-')
                ->options(fn () => JamPelajaran::opsi())
                ->required()
                ->helperText('Jam ke-1 atau ke-2 menentukan guru boleh mengabsen kelas lewat aplikasi.'),

            Select::make('jam_ke_selesai')
                ->label('Sampai jam ke-')
                ->options(fn () => JamPelajaran::opsi())
                ->required()
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        if ((int) $value < (int) $get('jam_ke_mulai')) {
                            $fail('Jam selesai tidak boleh lebih awal dari jam mulai.');
                        }
                    },
                ])
        ]);
    }
}
