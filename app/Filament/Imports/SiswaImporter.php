<?php

namespace App\Filament\Imports;

use App\Models\Siswa;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\TahunAjaran;

class SiswaImporter extends Importer
{
    protected static ?string $model = Siswa::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nis')
                ->label('NIS')
                ->requiredMapping()
                ->rules(['required', 'max:20']),

            ImportColumn::make('nisn')
                ->label('NISN')
                ->rules(['nullable', 'max:20']),

            ImportColumn::make('nama')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('jenis_kelamin')
                ->label('Jenis kelamin (L/P)')
                ->requiredMapping()
                ->rules(['required', 'in:L,P,l,p'])
                ->castStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null),

            ImportColumn::make('agama')
                ->label('Agama (Islam/Kristen/Katolik/Hindu/Buddha/Konghucu)')
                ->rules([
                    'nullable',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $dikenal = collect(Siswa::AGAMA)
                            ->contains(fn ($a) => strtolower($a) === strtolower(trim((string) $value)));

                        if (filled($value) && ! $dikenal) {
                            $fail("Agama '{$value}' tidak dikenal.");
                        }
                    },
                ])
                ->fillRecordUsing(function (Siswa $record, ?string $state): void {
                    if (blank($state)) {
                        return; // kosong = tidak mengubah nilai yang sudah ada
                    }

                    $record->agama = collect(Siswa::AGAMA)
                        ->first(fn ($a) => strtolower($a) === strtolower(trim($state)));
                }),

            ImportColumn::make('nama_wali')
                ->rules(['nullable', 'max:100']),

            ImportColumn::make('hubungan_wali')
                ->label('Hubungan wali (ayah/ibu/wali)')
                ->rules(['nullable', 'max:30'])
                ->castStateUsing(fn (?string $state): ?string => $state ? strtolower(trim($state)) : null),

            ImportColumn::make('no_hp_wali')
                ->label('No. HP wali')
                ->rules(['nullable', 'max:20']),

            ImportColumn::make('kelas')
                ->label('Kelas (nama persis, contoh: XI RPL 1)')
                ->fillRecordUsing(fn () => null) // bukan kolom tabel siswa, jangan disimpan ke sana
                ->rules([
                    'nullable',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $ta = TahunAjaran::aktif();

                        if (! $ta) {
                            $fail('Tahun ajaran aktif belum diatur.');
                            return;
                        }

                        $ada = Kelas::where('tahun_ajaran_id', $ta->id)
                            ->where('nama', trim((string) $value))
                            ->exists();

                        if (! $ada) {
                            $fail("Kelas '{$value}' tidak ditemukan di tahun ajaran aktif.");
                        }
                    },
                ]),
        ];
    }

    // NIS sudah ada = data diperbarui; NIS baru = siswa baru
    public function resolveRecord(): ?Siswa
    {
        return Siswa::firstOrNew(['nis' => $this->data['nis']]);
    }

    // Dijalankan setelah siswa tersimpan: masukkan ke kelas pada tahun ajaran aktif
    protected function afterSave(): void
    {
        $nama = trim((string) ($this->data['kelas'] ?? ''));

        if ($nama === '') {
            return;
        }

        $ta = TahunAjaran::aktif();
        $kelas = Kelas::where('tahun_ajaran_id', $ta?->id)->where('nama', $nama)->first();

        if (! $kelas) {
            return; // sudah ditolak oleh aturan validasi di atas
        }

        KelasSiswa::updateOrCreate(
            ['tahun_ajaran_id' => $ta->id, 'siswa_id' => $this->record->id],
            ['kelas_id' => $kelas->id]
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import siswa selesai. ' . number_format($import->successful_rows) . ' baris berhasil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal.';
        }

        return $body;
    }
}