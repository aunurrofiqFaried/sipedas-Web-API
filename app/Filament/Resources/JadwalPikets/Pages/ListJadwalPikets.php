<?php

namespace App\Filament\Resources\JadwalPikets\Pages;

use App\Filament\Resources\JadwalPikets\JadwalPiketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJadwalPikets extends ListRecords
{
    protected static string $resource = JadwalPiketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
