<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->unsignedInteger('kapasitas_peserta')->nullable()->after('nama_kelas');
        });
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropColumn('kapasitas_peserta');
        });
    }
};
