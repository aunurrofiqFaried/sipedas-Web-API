<?php

namespace App\Filament\Resources\JadwalPikets;

use App\Filament\Resources\JadwalPikets\Pages\CreateJadwalPiket;
use App\Filament\Resources\JadwalPikets\Pages\EditJadwalPiket;
use App\Filament\Resources\JadwalPikets\Pages\ListJadwalPikets;
use App\Filament\Resources\JadwalPikets\Schemas\JadwalPiketForm;
use App\Filament\Resources\JadwalPikets\Tables\JadwalPiketsTable;
use App\Models\JadwalPiket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\PembatasAkses;
use App\Models\User;
use UnitEnum;

class JadwalPiketResource extends Resource
{
    use PembatasAkses;

    protected static ?string $modelLabel = 'Jadwal Piket';
    protected static ?string $pluralModelLabel = 'Jadwal Piket';
    protected static ?string $navigationLabel = 'Jadwal Piket';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    // Kepala sekolah melihat; waka kesiswaan dan admin yang mengatur
    protected static function rolePelihat(): array
    {
        return [User::ROLE_KEPALA_SEKOLAH];
    }

    protected static function roleEditor(): array
    {
        return [User::ROLE_KESISWAAN];
    }
    protected static ?string $model = JadwalPiket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return JadwalPiketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JadwalPiketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwalPikets::route('/'),
            'create' => CreateJadwalPiket::route('/create'),
            'edit' => EditJadwalPiket::route('/{record}/edit'),
        ];
    }
}
