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
        Schema::create('khs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_khs')->constrained('khs', 'id');
            $table->foreignUuid('id_mk')->constrained('mata_kuliah', 'id');
            $table->string('nilai_huruf');
            $table->decimal('bobot', 3, 2);
            $table->integer('sks');
            $table->foreignUuid('id_nilai')->constrained('nilai', 'id')->nullable(); // referensi ke tabel nilai
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khs_detail');
    }
};
