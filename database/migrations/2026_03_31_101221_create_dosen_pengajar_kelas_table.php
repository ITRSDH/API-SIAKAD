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
        Schema::create('dosen_pengajar_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_kelas_kuliah');
            $table->uuid('id_registrasi_dosen');

            // Atribut untuk Beban Kerja Dosen (BKD)
            $table->decimal('sks_substansi_total', 5, 2); // SKS yang diakui untuk dosen ini
            $table->integer('rencana_tatap_muka')->default(16);
            $table->integer('realisasi_tatap_muka')->default(0);
            $table->integer('urutan')->default(1); // Dosen ke-1, ke-2, dst.

            $table->foreign('id_kelas_kuliah')->references('id')->on('kelas_kuliah')->onDelete('cascade');
            $table->foreign('id_registrasi_dosen')->references('id')->on('dosen')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_pengajar_kelas');
    }
};
