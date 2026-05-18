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
        Schema::create('kelas_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi');
            $table->uuid('id_kurikulum_mata_kuliah');
            $table->uuid('id_semester');
            $table->string('nama_kelas');
            $table->string('bahasan')->nullable();
            $table->string('lingkup')->nullable()->comment('Internal/Eksternal/Campuran');
            $table->string('mode_kuliah')->nullable()->comment('Online/Offline/Campuran');
            $table->date('tanggal_mulai_efektif')->nullable();
            $table->date('tanggal_akhir_efektif')->nullable();

            // Foreign keys
            $table->foreign('id_kurikulum_mata_kuliah')->references('id')->on('kurikulum_mata_kuliah')->onDelete('cascade');
            $table->foreign('id_prodi')->references('id')->on('prodi')->onDelete('cascade');
            $table->foreign('id_semester')->references('id')->on('semester')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas_kuliah');
    }
};
