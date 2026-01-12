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
        Schema::create('prodi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_prodi', 10)->unique();
            $table->string('nama_prodi', 100);
            $table->foreignUuid('id_jenjang_pendidikan')->constrained('jenjang_pendidikan', 'id');
            $table->foreignUuid('id_kaprodi')->constrained('dosen', 'id');
            $table->string('akreditasi', 10)->nullable();
            $table->year('tahun_berdiri')->nullable();
            $table->integer('kuota')->default(0);
            $table->string('gelar_lulusan', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi');
    }
};
