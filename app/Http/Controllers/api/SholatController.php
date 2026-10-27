<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\AbsensiSholat;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SholatController extends Controller
{
    // GET /api/sholat/kelas-saya
    // Kelas yang diwalikan user, beserta kesiapan dan progres sholat hari ini
    public function kelasSaya(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $today = now()->toDateString();

        $data = $this->kelasWali($request->user(), $ta)
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $k) use ($ta, $today) {
                $sudah = AbsensiSholat::where('kelas_id', $k->id)
                    ->where('tanggal', $today)
                    ->selectRaw('waktu, COUNT(*) as total')
                    ->groupBy('waktu')
                    ->pluck('total', 'waktu');

                return [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'jumlah_peserta' => $this->peserta($k, $ta)->count(),
                    'absensi_harian_siap' => Absensi::where('kelas_id', $k->id)
                        ->where('tanggal', $today)
                        ->exists(),
                    'dhuhur' => (int) ($sudah['dhuhur'] ?? 0),
                    'ashar' => (int) ($sudah['ashar'] ?? 0),
                ];
            });

        return response()->json(['tanggal' => $today, 'data' => $data]);
    }

    // GET /api/sholat/{waktu}/kelas/{kelas}
    public function siswa(Request $request, string $waktu, Kelas $kelas): JsonResponse
    {
        abort_unless(in_array($waktu, AbsensiSholat::WAKTU, true), 404, 'Waktu sholat tidak dikenal.');

        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $this->otorisasi($request->user(), $kelas, $ta);

        $today = now()->toDateString();

        $harian = Absensi::where('kelas_id', $kelas->id)
            ->where('tanggal', $today)
            ->pluck('status', 'siswa_id');

        abort_if(
            $harian->isEmpty(),
            422,
            'Absensi harian kelas ini belum diisi. Isi absensi kelas terlebih dahulu.'
        );

        $tersimpan = AbsensiSholat::where('kelas_id', $kelas->id)
            ->where('tanggal', $today)
            ->where('waktu', $waktu)
            ->pluck('status', 'siswa_id');

        $siswa = $this->peserta($kelas, $ta)
            ->orderBy('siswa.nama')
            ->get(['siswa.id', 'siswa.nis', 'siswa.nama', 'siswa.jenis_kelamin'])
            ->map(function ($s) use ($harian, $tersimpan) {
                $statusHarian = $harian->get($s->id); // hadir/sakit/izin/alpa atau null
                $terkunci = $statusHarian !== 'hadir';

                return [
                    'id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'status_harian' => $statusHarian,
                    'terkunci' => $terkunci,
                    'status' => $terkunci ? null : $tersimpan->get($s->id),
                ];
            });

        return response()->json([
            'tanggal' => $today,
            'waktu' => $waktu,
            'kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama],
            'data' => $siswa,
        ]);
    }

    // POST /api/sholat
    // Menyimpan ulang pada hari yang sama = memperbarui
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'waktu' => ['required', Rule::in(AbsensiSholat::WAKTU)],
            'items' => ['present', 'array'],
            'items.*.siswa_id' => ['required', 'integer', 'distinct'],
            'items.*.status' => ['required', Rule::in(AbsensiSholat::STATUS)],
        ]);

        $kelas = Kelas::findOrFail($data['kelas_id']);
        $this->otorisasi($user, $kelas, $ta);

        $today = now()->toDateString();

        $harian = Absensi::where('kelas_id', $kelas->id)
            ->where('tanggal', $today)
            ->pluck('status', 'siswa_id');

        abort_if(
            $harian->isEmpty(),
            422,
            'Absensi harian kelas ini belum diisi. Isi absensi kelas terlebih dahulu.'
        );

        // Siswa yang boleh ditandai: muslim, aktif, dan hadir pada absensi pagi
        $layak = $this->peserta($kelas, $ta)
            ->get(['siswa.id', 'siswa.jenis_kelamin'])
            ->filter(fn ($s) => $harian->get($s->id) === 'hadir')
            ->keyBy('id');

        $items = collect($data['items'])->map(fn ($i) => [
            'siswa_id' => (int) $i['siswa_id'],
            'status' => $i['status'],
        ]);

        $ids = $items->pluck('siswa_id');

        abort_unless(
            $ids->diff($layak->keys())->isEmpty(),
            422,
            'Ada siswa yang tidak termasuk daftar sholat (non-muslim atau tidak hadir pagi).'
        );

        abort_unless(
            $layak->keys()->diff($ids)->isEmpty(),
            422,
            'Semua siswa pada daftar harus ditandai terlebih dahulu.'
        );

        foreach ($items as $i) {
            if ($i['status'] === 'berhalangan' && $layak[$i['siswa_id']]->jenis_kelamin !== 'P') {
                abort(422, 'Status Berhalangan hanya untuk siswi.');
            }
        }

        $now = now();

        $rows = $items->map(fn ($i) => [
            'siswa_id' => $i['siswa_id'],
            'kelas_id' => $kelas->id,
            'tanggal' => $today,
            'waktu' => $data['waktu'],
            'status' => $i['status'],
            'diinput_oleh' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AbsensiSholat::upsert(
            $rows,
            ['siswa_id', 'tanggal', 'waktu'],
            ['kelas_id', 'status', 'diinput_oleh', 'updated_at']
        );

        return response()->json([
            'message' => 'Absensi sholat tersimpan.',
            'waktu' => $data['waktu'],
            'tanggal' => $today,
            'jumlah' => count($rows),
        ]);
    }

    // Kelas yang diwalikan user pada tahun ajaran aktif (admin: semua kelas)
    private function kelasWali(User $user, TahunAjaran $ta): Builder
    {
        $query = Kelas::query()->where('tahun_ajaran_id', $ta->id);

        return $user->role === User::ROLE_ADMIN
            ? $query
            : $query->where('wali_kelas_id', $user->id);
    }

    private function otorisasi(User $user, Kelas $kelas, TahunAjaran $ta): void
    {
        abort_unless(
            $this->kelasWali($user, $ta)->whereKey($kelas->id)->exists(),
            403,
            'Hanya wali kelas yang dapat mengabsen sholat kelas ini.'
        );
    }

    // Siswa muslim yang aktif di kelas ini pada tahun ajaran aktif
    private function peserta(Kelas $kelas, TahunAjaran $ta): Builder
    {
        return Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('kelas_siswa.tahun_ajaran_id', $ta->id)
            ->where('siswa.is_active', true)
            ->where('siswa.agama', 'Islam');
    }
}