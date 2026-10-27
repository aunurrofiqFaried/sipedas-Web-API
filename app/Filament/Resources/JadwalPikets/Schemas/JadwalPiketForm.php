<?php

namespace App\Filament\Resources\JadwalPikets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use App\Models\JamPelajaran;
use App\Models\User;

class JadwalPiketForm
{
    public static function configure(Schema $schema): Schema
    {
    return $schema->components([
        Select::make('tahun_ajaran_id')
            ->label('Tahun ajaran')
            ->relationship('tahunAjaran', 'nama')
            ->default(fn () => TahunAjaran::aktif()?->id)
            ->required(),

        // Saat MEMBUAT: boleh memilih banyak orang sekaligus
        Select::make('user_ids')
            ->label('Guru / Staf (boleh pilih banyak)')
            ->multiple()
            ->options(fn () => User::query()
                ->where('is_active', true)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $u) => [
                    $u->id => $u->name . ' · ' . (User::ROLE_LABELS[$u->role] ?? $u->role),
                ])
                ->all())
            ->searchable()
            ->required()
            ->visibleOn('create'),

        // Saat MENGUBAH: satu orang saja
        Select::make('user_id')
            ->label('Guru / Staf')
            ->relationship(
                'user',
                'name',
                modifyQueryUsing: fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('role', '!=', 'admin'),
            )
            ->searchable()
            ->preload()
            ->required()
            ->visibleOn('edit'),

        Select::make('hari')
            ->options(Jadwal::HARI)
            ->required(),

        Select::make('jam_ke_mulai')
            ->label('Mulai jam ke-')
            ->options(fn () => JamPelajaran::opsi())
            ->required(),

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
            ]),

        TextInput::make('tugas')
            ->label('Tugas piket (opsional)')
            ->placeholder('Contoh: Gerbang pagi')
            ->maxLength(100),
    ]);
    }
}
