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
        Schema::create('jadwal_piket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari'); // 1=Senin ... 5=Jumat
            $table->string('tugas', 100)->nullable(); // contoh: Gerbang pagi
            $table->timestamps();

            // satu orang hanya sekali per hari dalam satu tahun ajaran
            $table->unique(['tahun_ajaran_id', 'user_id', 'hari']);
            $table->index(['tahun_ajaran_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_piket');
    }
};
