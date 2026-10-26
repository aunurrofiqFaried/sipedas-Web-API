<?php

namespace App\Filament\Resources\JadwalPikets\Pages;

use App\Filament\Resources\JadwalPikets\JadwalPiketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Services\PiketBentrokService;

class EditJadwalPiket extends EditRecord
{
    protected static string $resource = JadwalPiketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(PiketBentrokService::class);
        $pesan = $service->cek([$data['user_id']], $data, $this->record->id);

        if ($pesan !== []) {
            $service->peringatkan($pesan);
            $this->halt();
        }

        return $data;
    }
}
