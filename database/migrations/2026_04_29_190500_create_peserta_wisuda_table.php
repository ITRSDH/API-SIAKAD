<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_wisuda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_periode_wisuda')->constrained('periode_wisuda', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_kelulusan')->constrained('kelulusan', 'id')->cascadeOnDelete();
            $table->date('tanggal_daftar')->nullable();
            $table->enum('status', ['draft', 'terdaftar', 'terverifikasi', 'hadir', 'batal'])->default('draft');
            $table->enum('status_validasi_administrasi', ['belum', 'memenuhi', 'tidak_memenuhi'])->default('belum');
            $table->string('nomor_peserta', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_periode_wisuda', 'id_mahasiswa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_wisuda');
    }
};
