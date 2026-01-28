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
        Schema::create('krs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id');
            $table->foreignUuid('id_semester')->constrained('semester', 'id');
            $table->date('tanggal_pengisian');
            $table->string('status', 20)->default('Menunggu Verifikasi');
            $table->date('tanggal_verifikasi')->nullable();
            $table->foreignUuid('id_dosen_wali')->nullable()->constrained('dosen', 'id');
            $table->string('catatan_verifikasi')->nullable();
            $table->integer('jumlah_sks_diambil');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('krs');
    }
};
