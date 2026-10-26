<?php

namespace App\Services;

use App\Models\JadwalPiket;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PiketBentrokService
{
    /**
     * @param  array<int>  $userIds
     * @return array<int, string> pesan bentrok, kosong jika aman
     */
    public function cek(array $userIds, array $data, ?int $ignoreId = null): array
    {
        return JadwalPiket::query()
            ->with('user:id,name')
            ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->where('hari', $data['hari'])
            ->whereIn('user_id', $userIds)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('jam_ke_mulai', '<=', $data['jam_ke_selesai'])
            ->where('jam_ke_selesai', '>=', $data['jam_ke_mulai'])
            ->get()
            ->map(fn (JadwalPiket $p) => "{$p->user?->name} sudah piket jam ke-{$p->jam_ke_mulai} sampai {$p->jam_ke_selesai} di hari itu.")
            ->unique()
            ->values()
            ->all();
    }

    public function peringatkan(array $pesan): void
    {
        Notification::make()
            ->title('Jadwal piket bentrok, belum disimpan')
            ->body(new HtmlString(implode('<br>', array_map('e', $pesan))))
            ->danger()
            ->persistent()
            ->send();
    }
}