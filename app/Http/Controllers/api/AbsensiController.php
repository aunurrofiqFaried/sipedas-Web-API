<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Wa;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Models\Jadwal;
use App\Models\JamPelajaran;

class AbsensiController extends Controller
{
    // GET /api/absensi/kelas-hari-ini
    // Kelas yang boleh diabsen user hari ini, beserta alasannya
    public function kelasHariIni(Request $request): JsonResponse
    {
        $user = $request->user();
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $today = now()->toDateString();

        // Kelas tempat guru ini mengajar di jam ke-1 atau ke-2 hari ini
        $kelasJadwal = Jadwal::where('tahun_ajaran_id', $ta->id)
            ->where('guru_id', $user->id)
            ->where('hari', now()->dayOfWeekIso)
            ->where('jam_ke_mulai', '<=', 2)
            ->pluck('kelas_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $urutan = ['admin' => 0, 'wali' => 0, 'jadwal' => 1, 'pengganti' => 2];

        $data = $this->kelasBolehAbsen($user, $ta)
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $k) use ($ta, $today, $user, $kelasJadwal) {
                $alasan = match (true) {
                    $user->role === User::ROLE_ADMIN => 'admin',
                    (int) $k->wali_kelas_id === (int) $user->id => 'wali',
                    in_array((int) $k->id, $kelasJadwal, true) => 'jadwal',
                    default => 'pengganti',
                };

                return [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'alasan' => $alasan,
                    'jumlah_siswa' => DB::table('kelas_siswa')
                        ->where('kelas_id', $k->id)
                        ->where('tahun_ajaran_id', $ta->id)
                        ->count(),
                    'sudah_diabsen' => Absensi::where('kelas_id', $k->id)
                        ->where('tanggal', $today)
                        ->count(),
                ];
            })
            ->sortBy(fn (array $k) => $urutan[$k['alasan']])
            ->values();

        return response()->json(['tanggal' => $today, 'data' => $data]);
    }

    // GET /api/kelas/{kelas}/siswa
    // Daftar siswa + status absensi hari ini (kalau sudah pernah diabsen)
    public function siswa(Request $request, Kelas $kelas): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        abort_unless(
            $this->kelasBolehAbsen($request->user(), $ta)->whereKey($kelas->id)->exists(),
            403,
            'Anda tidak berhak mengabsen kelas ini hari ini.'
        );

        $today = now()->toDateString();

        $absen = Absensi::where('kelas_id', $kelas->id)
            ->where('tanggal', $today)
            ->get()
            ->keyBy('siswa_id');

        $siswa = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('kelas_siswa.tahun_ajaran_id', $ta->id)
            ->where('siswa.is_active', true)
            ->orderBy('siswa.nama')
            ->get([
                'siswa.id', 'siswa.nis', 'siswa.nama', 'siswa.jenis_kelamin',
                'siswa.nama_wali', 'siswa.no_hp_wali',
            ])
            ->map(function ($s) use ($absen, $kelas, $today) {
                $status = $absen->get($s->id)?->status;

                return [
                    'id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'status' => $status,
                    'keterangan' => $absen->get($s->id)?->keterangan,
                    'ada_nomor_wali' => (bool) Wa::normalisasi($s->no_hp_wali),
                    // link hanya dibuat untuk siswa yang tidak hadir
                    'wa_link' => ($status && $status !== 'hadir')
                        ? Wa::link($s->no_hp_wali, $this->pesanTidakHadir($s, $kelas, $status, $today))
                        : null,
                ];
            });

        return response()->json([
            'tanggal' => $today,
            'kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama],
            'data' => $siswa,
        ]);
    }

    private function pesanTidakHadir($siswa, Kelas $kelas, string $status, string $tanggal): string
    {
        $label = match ($status) {
            'sakit' => 'sakit',
            'izin' => 'izin',
            default => 'tidak hadir tanpa keterangan',
        };

        $tgl = Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y');
        $wali = $siswa->nama_wali ?: 'Orang Tua/Wali';

        return "Assalamualaikum Bapak/Ibu {$wali}. "
            . "Kami dari pihak sekolah menginformasikan bahwa putra/putri Bapak/Ibu, "
            . "{$siswa->nama} (kelas {$kelas->nama}), pada {$tgl} tercatat {$label}. "
            . "Mohon konfirmasinya. Terima kasih.";
    }

    // POST /api/absensi
    // Simpan absensi satu kelas. Mengirim ulang pada hari yang sama = memperbarui.
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tanggal' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.siswa_id' => ['required', 'integer', 'distinct'],
            'items.*.status' => ['required', Rule::in(Absensi::STATUS)],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $kelas = Kelas::findOrFail($data['kelas_id']);

        abort_unless(
            $this->kelasBolehAbsen($user, $ta)->whereKey($kelas->id)->exists(),
            403,
            'Anda tidak berhak mengabsen kelas ini hari ini.'
        );

        // Tanggal selalu hari ini (jam server). Hanya admin yang boleh mengisi tanggal lain.
        $tanggal = ($user->role === User::ROLE_ADMIN && ! empty($data['tanggal']))
            ? Carbon::parse($data['tanggal'])->toDateString()
            : now()->toDateString();

        $ids = collect($data['items'])->pluck('siswa_id');

        $valid = DB::table('kelas_siswa')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->whereIn('siswa_id', $ids)
            ->count();

        abort_unless($valid === $ids->count(), 422, 'Ada siswa yang bukan anggota kelas ini.');

        $now = now();
        $rows = collect($data['items'])->map(fn ($i) => [
            'siswa_id' => $i['siswa_id'],
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'status' => $i['status'],
            'keterangan' => $i['keterangan'] ?? null,
            'diinput_oleh' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Absensi::upsert(
            $rows,
            ['siswa_id', 'tanggal'],
            ['kelas_id', 'status', 'keterangan', 'diinput_oleh', 'updated_at']
        );

        return response()->json([
            'message' => 'Absensi tersimpan.',
            'tanggal' => $tanggal,
            'jumlah' => count($rows),
        ]);
    }

    /**
     * Boleh mengabsen kelas X hari ini jika user adalah:
     *  - admin, atau
     *  - wali kelas X, atau
     *  - guru yang mengajar di kelas X hari ini pada jam ke-1/2, atau
     *  - orang yang sudah mengabsen kelas X hari ini (untuk mengubahnya hari itu juga), atau
     *  - siapa saja yang berstatus guru, kalau kelas X belum diabsen dan jam ke-2 sudah dimulai
     */
    private function kelasBolehAbsen(User $user, TahunAjaran $ta): Builder
    {
        $query = Kelas::query()->where('tahun_ajaran_id', $ta->id);

        if ($user->role === User::ROLE_ADMIN) {
            return $query;
        }

        $sekarang = now();
        $hari = $sekarang->dayOfWeekIso;
        $hariIni = $sekarang->toDateString();

        // Cadangan terbuka begitu jam ke-2 dimulai, hanya pada hari sekolah
        $jam2 = JamPelajaran::semua()->get(2);
        $penggantiBuka = $hari <= 5
            && $jam2 !== null
            && $sekarang->format('H:i:s') >= $jam2->jam_mulai;

        return $query->where(function (Builder $w) use ($user, $hari, $hariIni, $penggantiBuka) {
            $w->where('wali_kelas_id', $user->id)
                ->orWhereExists(function ($s) use ($user, $hari) {
                    $s->select(DB::raw(1))
                        ->from('jadwal')
                        ->whereColumn('jadwal.kelas_id', 'kelas.id')
                        ->where('jadwal.guru_id', $user->id)
                        ->where('jadwal.hari', $hari)
                        ->where('jadwal.jam_ke_mulai', '<=', 2);
                })
                ->orWhereExists(function ($s) use ($user, $hariIni) {
                    $s->select(DB::raw(1))
                        ->from('absensi')
                        ->whereColumn('absensi.kelas_id', 'kelas.id')
                        ->where('absensi.tanggal', $hariIni)
                        ->where('absensi.diinput_oleh', $user->id);
                });

            if ($penggantiBuka) {
                $w->orWhere(function (Builder $f) use ($hariIni) {
                    $f->has('siswa')
                        ->whereNotExists(function ($s) use ($hariIni) {
                            $s->select(DB::raw(1))
                                ->from('absensi')
                                ->whereColumn('absensi.kelas_id', 'kelas.id')
                                ->where('absensi.tanggal', $hariIni);
                        });
                });
            }
        });
    }
}
