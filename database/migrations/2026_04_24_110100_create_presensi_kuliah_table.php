<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_pertemuan_kuliah')->constrained('pertemuan_kuliah', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_krs_detail')->constrained('krs_detail', 'id')->cascadeOnDelete();
            $table->enum('status_kehadiran', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_pertemuan_kuliah', 'id_krs_detail']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_kuliah');
    }
};
