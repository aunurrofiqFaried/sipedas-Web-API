<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Services\JadwalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JurnalController extends Controller
{
    // GET /api/jurnal  (riwayat jurnal milik guru yang login)
    public function index(Request $request): JsonResponse
    {
        $jurnal = Jurnal::with(['kelas:id,nama', 'jadwal.mapel:id,nama'])
            ->where('guru_id', $request->user()->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($jurnal);
    }

    // POST /api/jurnal
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'materi' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $jadwal = Jadwal::findOrFail($data['jadwal_id']);

        abort_unless((int) $jadwal->guru_id === (int) $user->id, 403, 'Ini bukan jadwal Anda.');

        abort_unless(
            JadwalService::bisaDiisi($jadwal),
            422,
            'Jurnal hanya dapat diisi pada hari jadwal tersebut.'
        );

        $jurnal = Jurnal::updateOrCreate(
            ['jadwal_id' => $jadwal->id, 'tanggal' => now()->toDateString()],
            [
                'guru_id' => $user->id,
                'kelas_id' => $jadwal->kelas_id,
                'materi' => $data['materi'],
                'catatan' => $data['catatan'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Jurnal tersimpan.',
            'data' => $jurnal,
        ], $jurnal->wasRecentlyCreated ? 201 : 200);
    }
}
