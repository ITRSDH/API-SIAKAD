<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_khs')->constrained('khs', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_krs_detail')->nullable()->constrained('krs_detail', 'id')->nullOnDelete();
            $table->foreignUuid('id_kelas_kuliah')->nullable()->constrained('kelas_kuliah', 'id')->nullOnDelete();
            $table->string('kode_mk', 20)->nullable();
            $table->string('nama_mk')->nullable();
            $table->unsignedTinyInteger('sks')->default(0);
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->enum('status', ['terdaftar', 'drop', 'lulus', 'tidak_lulus'])->default('terdaftar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khs_detail');
    }
};
