<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            // nullable supaya data uji yang sudah ada tidak membuat migration gagal
            $table->unsignedTinyInteger('jam_ke_mulai')->nullable()->after('hari');
            $table->unsignedTinyInteger('jam_ke_selesai')->nullable()->after('jam_ke_mulai');

            // aturan "sekali per hari" diganti pengecekan jam yang beririsan
            $table->dropUnique(['tahun_ajaran_id', 'user_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropColumn(['jam_ke_mulai', 'jam_ke_selesai']);
            $table->unique(['tahun_ajaran_id', 'user_id', 'hari']);
        });
    }
};
