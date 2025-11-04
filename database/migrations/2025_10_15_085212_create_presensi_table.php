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
        Schema::create('presensi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_mk')->constrained('kelas_mk', 'id');
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id');
            $table->date('tanggal');
            $table->enum('status_hadir', ['Hadir', 'Sakit', 'Izin', 'Alpha']);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
