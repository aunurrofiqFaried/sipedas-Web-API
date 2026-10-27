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
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('agama', 20)->default('Islam')->after('jenis_kelamin');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn('agama');
        });
    }
};
