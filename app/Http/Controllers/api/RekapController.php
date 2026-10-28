<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RekapController extends Controller
{
    // GET /api/rekap/kelas
    // Semua kelas pada tahun ajaran aktif (untuk pilihan kelas)
    public function kelas(): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = Kelas::where('tahun_ajaran_id', $ta->id)
            ->withCount('siswa')
            ->orderBy('nama')
            ->get()
            ->map(fn (Kelas $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'jumlah_siswa' => $k->siswa_count,
            ]);

        return response()->json(['data' => $data]);
    }

    // GET /api/rekap/absensi?kelas_id=1&bulan=2026-10   (atau bulan=semua)
    public function absensi(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'bulan' => ['required', 'regex:/^(\d{4}-(0[1-9]|1[0-2])|semua)$/'],
        ]);

        $kelas = Kelas::where('tahun_ajaran_id', $ta->id)->findOrFail($data['kelas_id']);

        if ($data['bulan'] === 'semua') {
            $dari = $ta->tanggal_mulai->copy();
            $sampai = $ta->tanggal_selesai->copy();
            $label = 'Tahun ajaran ' . $ta->nama;
        } else {
            $dari = Carbon::createFromFormat('Y-m-d', $data['bulan'] . '-01')->startOfMonth();
            $sampai = $dari->copy()->endOfMonth();
            $label = $dari->locale('id')->translatedFormat('F Y');
        }

        $rentang = [$dari->toDateString(), $sampai->toDateString()];

        // Jumlah per siswa per status
        $rekap = Absensi::where('kelas_id', $kelas->id)
            ->whereBetween('tanggal', $rentang)
            ->selectRaw('siswa_id, status, COUNT(*) as total')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');

        $hariTercatat = Absensi::where('kelas_id', $kelas->id)
            ->whereBetween('tanggal', $rentang)
            ->distinct()
            ->count('tanggal');

        $siswa = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('kelas_siswa.tahun_ajaran_id', $ta->id)
            ->where('siswa.is_active', true)
            ->orderBy('siswa.nama')
            ->get(['siswa.id', 'siswa.nis', 'siswa.nama'])
            ->map(function ($s) use ($rekap) {
                $r = $rekap->get($s->id, collect())->pluck('total', 'status');

                return [
                    'id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'hadir' => (int) ($r['hadir'] ?? 0),
                    'sakit' => (int) ($r['sakit'] ?? 0),
                    'izin' => (int) ($r['izin'] ?? 0),
                    'alpa' => (int) ($r['alpa'] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama],
            'periode' => [
                'label' => $label,
                'dari' => $rentang[0],
                'sampai' => $rentang[1],
            ],
            'hari_tercatat' => $hariTercatat,
            'total' => [
                'hadir' => $siswa->sum('hadir'),
                'sakit' => $siswa->sum('sakit'),
                'izin' => $siswa->sum('izin'),
                'alpa' => $siswa->sum('alpa'),
            ],
            'siswa' => $siswa,
        ]);
    }
}