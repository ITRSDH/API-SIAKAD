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
            $table->string('jenjang_pendidikan', 100);
            $table->uuid('id_kaprodi')->nullable();
            $table->string('akreditasi', 10)->nullable();
            $table->year('tahun_berdiri')->nullable();
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
