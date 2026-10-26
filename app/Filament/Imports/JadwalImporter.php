<?php

namespace App\Filament\Imports;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Ruang;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\JadwalBentrokService;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use App\Models\JamPelajaran;

class JadwalImporter extends Importer
{
    protected static ?string $model = Jadwal::class;


    public static function getColumns(): array
    {
        return [
            // Empat kolom berikut bukan kolom tabel jadwal; dicocokkan manual di resolveRecord()
            ImportColumn::make('guru')
                ->label('Guru (username)')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('kelas')
                ->label('Kelas (nama persis)')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('mapel')
                ->label('Mapel (nama atau kode)')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('ruang')
                ->label('Ruang (nama persis, boleh kosong)')
                ->rules(['nullable'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('hari')
                ->label('Hari (Senin-Sabtu)')
                ->requiredMapping()
                ->rules([
                    'required',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $valid = array_map('strtolower', Jadwal::HARI);

                        if (! in_array(strtolower(trim((string) $value)), $valid, true)) {
                            $fail("Hari '{$value}' tidak dikenal. Isi Senin sampai Sabtu.");
                        }
                    },
                ])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('jam_ke_mulai')
                ->label('Jam ke- (mulai)')
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1', 'max:15']),

            ImportColumn::make('jam_ke_selesai')
                ->label('Jam ke- (selesai)')
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1', 'max:15', 'gte:jam_ke_mulai']),

        ];
    }

    public function resolveRecord(): ?Jadwal
    {
        $ta = TahunAjaran::aktif()
            ?? throw new RowImportFailedException('Tahun ajaran aktif belum diatur.');

        $username = strtolower(trim($this->data['guru']));
        $guru = User::where('username', $username)
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_GURU, User::ROLE_GURU_BK, User::ROLE_KESISWAAN, User::ROLE_KEPALA_SEKOLAH])
            ->first()
            ?? throw new RowImportFailedException("Guru dengan username '{$username}' tidak ditemukan atau tidak aktif.");

        $namaKelas = trim($this->data['kelas']);
        $kelas = Kelas::where('tahun_ajaran_id', $ta->id)->where('nama', $namaKelas)->first()
            ?? throw new RowImportFailedException("Kelas '{$namaKelas}' tidak ditemukan di tahun ajaran aktif.");

        $namaMapel = trim($this->data['mapel']);
        $mapel = Mapel::where(fn ($q) => $q->where('nama', $namaMapel)->orWhere('kode', $namaMapel))->first()
            ?? throw new RowImportFailedException("Mapel '{$namaMapel}' tidak ditemukan.");

        $ruang = null;
        $namaRuang = trim((string) ($this->data['ruang'] ?? ''));
        if ($namaRuang !== '') {
            $ruang = Ruang::where('nama', $namaRuang)->first()
                ?? throw new RowImportFailedException("Ruang '{$namaRuang}' tidak ditemukan.");
        }

        $hari = array_search(
            strtolower(trim($this->data['hari'])),
            array_map('strtolower', Jadwal::HARI),
            true
        );

        $jamMulai = JamPelajaran::where('jam_ke', (int) $this->data['jam_ke_mulai'])->first()
            ?? throw new RowImportFailedException("Jam ke-{$this->data['jam_ke_mulai']} belum diatur di Pengaturan Jam Pelajaran.");

        $jamSelesai = JamPelajaran::where('jam_ke', (int) $this->data['jam_ke_selesai'])->first()
            ?? throw new RowImportFailedException("Jam ke-{$this->data['jam_ke_selesai']} belum diatur di Pengaturan Jam Pelajaran.");

        $jadwal = Jadwal::firstOrNew([
            'tahun_ajaran_id' => $ta->id,
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'hari' => $hari,
            'jam_ke_mulai' => (int) $this->data['jam_ke_mulai'],
        ]);

        $jadwal->ruang_id = $ruang?->id;
        $jadwal->jam_mulai = $jamMulai->jam_mulai;
        $jadwal->jam_selesai = $jamSelesai->jam_selesai;

        return $jadwal;
    }

    protected function beforeSave(): void
    {
        $pesan = app(JadwalBentrokService::class)->cek([
            'tahun_ajaran_id' => $this->record->tahun_ajaran_id,
            'hari' => $this->record->hari,
            'jam_mulai' => $this->record->jam_mulai,
            'jam_selesai' => $this->record->jam_selesai,
            'guru_id' => $this->record->guru_id,
            'kelas_id' => $this->record->kelas_id,
            'ruang_id' => $this->record->ruang_id,
        ], $this->record->id);

        if ($pesan !== []) {
            throw new RowImportFailedException(implode(' ', $pesan));
        }
    }


    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import jadwal selesai. ' . number_format($import->successful_rows) . ' baris berhasil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal.';
        }

        return $body;
    }
}