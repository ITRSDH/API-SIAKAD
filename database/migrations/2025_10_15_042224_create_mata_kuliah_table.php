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
            $table->string('kode_mk');
            $table->string('nama_mk');
            $table->integer('sks');
            $table->integer('semester_rekomendasi');
            $table->enum('jenis', ['Wajib', 'Pilihan']);
            $table->text('deskripsi')->nullable();

            $table->integer('teori')->default(0);
            $table->integer('seminar')->default(0);
            $table->integer('praktikum')->default(0);
            $table->integer('praktek_klinik')->default(0);

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
