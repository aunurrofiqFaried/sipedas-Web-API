<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi_sholat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('waktu', ['dhuhur', 'ashar']);
            $table->enum('status', ['hadir', 'tidak_hadir', 'berhalangan']);
            $table->foreignId('diinput_oleh')->constrained('users');
            $table->timestamps();

            // satu siswa satu catatan per waktu sholat per hari; menyimpan ulang = memperbarui
            $table->unique(['siswa_id', 'tanggal', 'waktu']);
            $table->index(['kelas_id', 'tanggal', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_sholat');
    }
};
