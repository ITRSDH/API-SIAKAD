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
        Schema::create('mata_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi');
            $table->string('kode_mk', 20);
            $table->string('nama_mk');

            $table->unsignedTinyInteger('sks')->nullable()->default(0);

            $table->unsignedTinyInteger('sks_tatap_muka')->nullable()->default(0);
            $table->unsignedTinyInteger('sks_praktikum')->nullable()->default(0);
            $table->unsignedTinyInteger('sks_praktek_lapangan')->nullable()->default(0);
            $table->unsignedTinyInteger('sks_simulasi')->nullable()->default(0);

            $table->enum('jenis_mk', ['wajib_prodi', 'wajib_nasional', 'pilihan', 'peminatan', 'tugas_akhir/skripsi/tesis/disertasi']);
            $table->enum('kelompok_mk', ['MPK', 'MKK', 'MKB', 'MPB', 'MBB', 'MKDK']);

            $table->timestamps();
            $table->unique(['id_prodi', 'kode_mk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_kuliah');
    }
};
