<?php

namespace App\Filament\Resources\JadwalPikets\Pages;

use App\Filament\Resources\JadwalPikets\JadwalPiketResource;
use App\Models\JadwalPiket;
use App\Services\PiketBentrokService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateJadwalPiket extends CreateRecord
{
    protected static string $resource = JadwalPiketResource::class;

    protected int $jumlahDibuat = 0;

    // Satu isian form = satu baris jadwal untuk setiap orang yang dipilih
    protected function handleRecordCreation(array $data): Model
    {
        $userIds = array_values(array_unique($data['user_ids']));

        $service = app(PiketBentrokService::class);
        $pesan = $service->cek($userIds, $data);

        if ($pesan !== []) {
            $service->peringatkan($pesan);
            $this->halt();
        }

        return DB::transaction(function () use ($userIds, $data) {
            $pertama = null;

            foreach ($userIds as $id) {
                $baris = JadwalPiket::create([
                    'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                    'user_id' => $id,
                    'hari' => $data['hari'],
                    'jam_ke_mulai' => $data['jam_ke_mulai'],
                    'jam_ke_selesai' => $data['jam_ke_selesai'],
                    'tugas' => $data['tugas'] ?? null,
                ]);

                $pertama ??= $baris;
                $this->jumlahDibuat++;
            }

            return $pertama;
        });
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "{$this->jumlahDibuat} jadwal piket dibuat";
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}