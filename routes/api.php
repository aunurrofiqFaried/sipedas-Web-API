<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\JurnalController;
use App\Http\Controllers\Api\PelanggaranController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KontakWaliController;
use App\Http\Controllers\Api\SholatController;
use App\Http\Controllers\Api\RekapController;

// Publik: login dibatasi 5 percobaan per menit
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Wajib login (token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/ubah-password', [AuthController::class, 'ubahPassword'])
    ->middleware('throttle:5,1');

    // Fitur harian guru (admin selalu lolos di middleware role)
    Route::middleware('role:guru,guru_bk,kesiswaan,kepala_sekolah')->group(function () {
        Route::get('/jadwal/hari-ini', [JadwalController::class, 'hariIni']);
        Route::get('/jadwal/saya', [JadwalController::class, 'saya']);

        Route::get('/absensi/kelas-hari-ini', [AbsensiController::class, 'kelasHariIni']);
        Route::get('/kelas/{kelas}/siswa', [AbsensiController::class, 'siswa']);
        Route::post('/absensi', [AbsensiController::class, 'store']);

        Route::get('/jurnal', [JurnalController::class, 'index']);
        Route::post('/jurnal', [JurnalController::class, 'store']);
        Route::get('/jurnal/bulan', [JurnalController::class, 'bulan']);

        Route::get('/pelanggaran/jenis', [PelanggaranController::class, 'jenis']);
        Route::get('/siswa/cari', [PelanggaranController::class, 'cariSiswa']);
        Route::post('/pelanggaran', [PelanggaranController::class, 'store']);

        Route::get('/sholat/kelas-saya', [SholatController::class, 'kelasSaya']);
        Route::get('/sholat/{waktu}/kelas/{kelas}', [SholatController::class, 'siswa']);
        Route::post('/sholat', [SholatController::class, 'store']);

        Route::get('/rekap/kelas', [RekapController::class, 'kelas']);
        Route::get('/rekap/absensi', [RekapController::class, 'absensi']);
    });

    // Melihat riwayat pelanggaran siswa (kepala sekolah boleh melihat)
    Route::middleware('role:guru,guru_bk,kesiswaan,kepala_sekolah')->group(function () {
        Route::get('/siswa/{siswa}/pelanggaran', [PelanggaranController::class, 'riwayat']);
    });

    // Sisi guru BK dan kesiswaan (admin otomatis lolos)
    Route::middleware('role:guru_bk,kesiswaan')->group(function () {
        Route::get('/bk/siswa/{siswa}/kontak-wali', [KontakWaliController::class, 'show']);
        Route::post('/bk/siswa/{siswa}/kontak-wali', [KontakWaliController::class, 'store']);
});
});
