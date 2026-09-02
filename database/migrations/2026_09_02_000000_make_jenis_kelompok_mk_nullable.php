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
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->enum('jenis_mk', ['wajib_prodi', 'wajib_nasional', 'pilihan', 'peminatan', 'tugas_akhir/skripsi/tesis/disertasi'])->nullable()->change();
            $table->enum('kelompok_mk', ['MPK', 'MKK', 'MKB', 'MPB', 'MBB', 'MKDK'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->enum('jenis_mk', ['wajib_prodi', 'wajib_nasional', 'pilihan', 'peminatan', 'tugas_akhir/skripsi/tesis/disertasi'])->nullable(false)->change();
            $table->enum('kelompok_mk', ['MPK', 'MKK', 'MKB', 'MPB', 'MBB', 'MKDK'])->nullable(false)->change();
        });
    }
};
