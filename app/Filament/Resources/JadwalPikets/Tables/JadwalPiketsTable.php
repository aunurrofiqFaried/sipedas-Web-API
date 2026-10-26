<?php

namespace App\Filament\Resources\JadwalPikets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use App\Models\JadwalPiket;
use Illuminate\Database\Eloquent\Builder;

class JadwalPiketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable(),

                TextColumn::make('user.role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => User::ROLE_LABELS[$state] ?? (string) $state),

                TextColumn::make('jam_ke_mulai')
                    ->label('Jam')
                    ->state(fn (JadwalPiket $record): string => $record->labelJam()),

                TextColumn::make('tugas')
                    ->placeholder('-'),
            ])
            ->groups([
                Group::make('hari')
                    ->label('Hari')
                    ->getTitleFromRecordUsing(fn ($record): string => Jadwal::HARI[$record->hari] ?? (string) $record->hari),
            ])
            ->defaultGroup('hari')
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun ajaran')
                    ->relationship('tahunAjaran', 'nama')
                    ->default(fn () => TahunAjaran::aktif()?->id),

                SelectFilter::make('hari')
                    ->options(Jadwal::HARI),

                SelectFilter::make('user_id')
                    ->label('Guru / Staf')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('hari')->orderBy('jam_ke_mulai'))
            ->paginated([25, 50])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
