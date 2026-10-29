<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use App\Services\JadwalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use Illuminate\Support\Carbon;

class JadwalController extends Controller
{
    // GET /api/jadwal/hari-ini
    public function hariIni(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $now = now();

        $jadwal = Jadwal::with(['kelas:id,nama', 'mapel:id,nama', 'ruang:id,nama,tipe'])
            ->where('guru_id', $request->user()->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->where('hari', $now->dayOfWeekIso)
            ->orderBy('jam_mulai')
            ->get();

        $sudahJurnal = Jurnal::whereIn('jadwal_id', $jadwal->pluck('id'))
            ->where('tanggal', $now->toDateString())
            ->pluck('jadwal_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $jadwal->map(fn (Jadwal $j) => [
            'id' => $j->id,
            'kelas' => $j->kelas,
            'mapel' => $j->mapel,
            'ruang' => $j->ruang,
            'jam_ke_mulai' => $j->jam_ke_mulai,
            'jam_ke_selesai' => $j->jam_ke_selesai,
            'jam_mulai' => substr($j->jam_mulai, 0, 5),
            'jam_selesai' => substr($j->jam_selesai, 0, 5),
            'sudah_jurnal' => in_array((int) $j->id, $sudahJurnal, true),
            'bisa_isi_jurnal' => JadwalService::bisaDiisi($j, $now),
        ]);

        // Piket guru ini hari ini (hanya yang sudah punya jam)
        $jam = JamPelajaran::semua();

        $piket = JadwalPiket::where('user_id', $request->user()->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->where('hari', $now->dayOfWeekIso)
            ->whereNotNull('jam_ke_mulai')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->map(fn (JadwalPiket $p) => [
                'jam_ke_mulai' => (int) $p->jam_ke_mulai,
                'jam_ke_selesai' => (int) $p->jam_ke_selesai,
                'jam_mulai' => substr((string) $jam->get($p->jam_ke_mulai)?->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $jam->get($p->jam_ke_selesai)?->jam_selesai, 0, 5),
                'tugas' => $p->tugas,
            ]);

        return response()->json([
            'tanggal' => $now->toDateString(),
            'jam_server' => $now->format('H:i:s'),
            'data' => $data,
            'piket' => $piket,
        ]);
    }
    // GET /api/jadwal/saya
    // Seluruh jadwal mingguan guru pada tahun ajaran aktif + rentang tanggalnya
    public function saya(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = Jadwal::with(['kelas:id,nama', 'mapel:id,nama', 'ruang:id,nama,tipe'])
            ->where('guru_id', $request->user()->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (Jadwal $j) => [
                'id' => $j->id,
                'hari' => (int) $j->hari,
                'kelas' => $j->kelas,
                'mapel' => $j->mapel,
                'ruang' => $j->ruang,
                'jam_ke_mulai' => $j->jam_ke_mulai,
                'jam_ke_selesai' => $j->jam_ke_selesai,
                'jam_mulai' => substr($j->jam_mulai, 0, 5),
                'jam_selesai' => substr($j->jam_selesai, 0, 5),
            ]);
        // Piket mingguan guru ini (hanya yang sudah punya jam)
        $jam = JamPelajaran::semua();

        $piket = JadwalPiket::where('user_id', $request->user()->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->whereNotNull('jam_ke_mulai')
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->map(fn (JadwalPiket $p) => [
                'id' => $p->id,
                'hari' => (int) $p->hari,
                'jam_ke_mulai' => (int) $p->jam_ke_mulai,
                'jam_ke_selesai' => (int) $p->jam_ke_selesai,
                'jam_mulai' => substr((string) $jam->get($p->jam_ke_mulai)?->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $jam->get($p->jam_ke_selesai)?->jam_selesai, 0, 5),
                'tugas' => $p->tugas,
            ]);

        return response()->json([
            'tahun_ajaran' => [
                'nama' => $ta->nama,
                'tanggal_mulai' => $ta->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $ta->tanggal_selesai->toDateString(),
            ],
            'data' => $data,
            'piket' => $piket,
        ]);
    }
    
    // GET /api/jurnal/bulan?bulan=2026-10
    // Semua jurnal milik guru yang login pada satu bulan
    public function bulan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $awal = Carbon::createFromFormat('Y-m-d', $data['bulan'] . '-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        $jurnal = Jurnal::with(['kelas:id,nama', 'jadwal.mapel:id,nama'])
            ->where('guru_id', $request->user()->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->map(fn (Jurnal $j) => [
                'id' => $j->id,
                'jadwal_id' => $j->jadwal_id,
                'tanggal' => $j->tanggal->toDateString(),
                'materi' => $j->materi,
                'catatan' => $j->catatan,
                'kelas' => $j->kelas,
                'mapel' => $j->jadwal?->mapel,
                'jam_ke_mulai' => $j->jadwal?->jam_ke_mulai,
                'jam_ke_selesai' => $j->jadwal?->jam_ke_selesai,
                'jam_mulai' => $j->jadwal ? substr($j->jadwal->jam_mulai, 0, 5) : null,
                'jam_selesai' => $j->jadwal ? substr($j->jadwal->jam_selesai, 0, 5) : null,
            ]);

        return response()->json(['bulan' => $data['bulan'], 'data' => $jurnal]);
    }
}
