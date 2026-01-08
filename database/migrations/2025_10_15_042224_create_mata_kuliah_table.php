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
            $table->foreignUuid('id_kurikulum')->constrained('kurikulum', 'id');
            $table->string('kode_mk')->unique();
            $table->string('nama_mk');

            // TOTAL SKS (Jumlah)
            $table->unsignedTinyInteger('sks');

            // Distribusi SKS
            $table->unsignedTinyInteger('teori')->default(0);            // T
            $table->unsignedTinyInteger('praktikum')->default(0);        // P
            $table->unsignedTinyInteger('klinik')->default(0);   // K

            $table->unsignedTinyInteger('semester_rekomendasi');

            $table->timestamps();
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
