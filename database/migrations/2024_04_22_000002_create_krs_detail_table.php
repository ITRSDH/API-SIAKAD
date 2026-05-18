<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_krs');
            $table->uuid('id_kelas_kuliah');
            $table->enum('status', ['terdaftar', 'drop', 'lulus', 'tidak_lulus'])->default('terdaftar');
            $table->text('catatan')->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->timestamps();

            $table->unique(['id_krs', 'id_kelas_kuliah']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_detail');
    }
};
