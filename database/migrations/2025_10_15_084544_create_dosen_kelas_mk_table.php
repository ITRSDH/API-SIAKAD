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
        Schema::create('dosen_kelas_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_mk')->constrained('kelas_mk', 'id');
            $table->foreignUuid('id_dosen')->constrained('dosen', 'id');
            // $table->enum('peran', ['Koordinator', 'Asisten'])->default('Koordinator');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_kelas_mk');
    }
};
