<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_komponen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_komponen_penilaian')->constrained('komponen_penilaian', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_krs_detail')->constrained('krs_detail', 'id')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_komponen_penilaian', 'id_krs_detail'], 'nilai_komponen_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_komponen');
    }
};
