<?php

namespace App\Filament\Imports;

use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('username')
                ->requiredMapping()
                ->rules(['required', 'max:255', 'regex:/^[a-z0-9._-]+$/i'])
                ->castStateUsing(fn (?string $state): ?string => $state ? strtolower(trim($state)) : null),

            ImportColumn::make('name')
                ->label('Nama lengkap')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('nip')
                ->label('NIP')
                ->rules(['nullable', 'max:30']),

            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255']),

            // admin sengaja tidak diizinkan lewat import
            ImportColumn::make('role')
                ->label('Role (guru / guru_bk / kesiswaan / kepala_sekolah)')
                ->requiredMapping()
                ->rules(['required', 'in:guru,guru_bk,kesiswaan,kepala_sekolah,staf']),

            // password hanya diubah kalau kolomnya terisi
            ImportColumn::make('password')
                ->rules(['nullable', 'min:8', 'max:100'])
                ->fillRecordUsing(function (User $record, ?string $state): void {
                    if (filled($state)) {
                        $record->password = $state;
                    }
                }),
        ];
    }

    // username sudah ada = data diperbarui; username baru = akun baru
    public function resolveRecord(): ?User
    {
        $user = User::firstOrNew(['username' => strtolower(trim($this->data['username']))]);

        if (! $user->exists) {
            $user->is_active = true;
        }

        return $user;
    }

    protected function beforeSave(): void
    {
        if ($this->record->getOriginal('role') === User::ROLE_ADMIN) {
            throw new RowImportFailedException('Akun admin tidak boleh diubah lewat import.');
        }

        if (! $this->record->exists && blank($this->record->password)) {
            throw new RowImportFailedException('Password wajib diisi untuk akun baru.');
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import guru selesai. ' . number_format($import->successful_rows) . ' baris berhasil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal.';
        }

        return $body;
    }
}