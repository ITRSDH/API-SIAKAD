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
        Schema::create('beban_ajar_dosen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_dosen')->constrained('dosen', 'id');
            $table->foreignUuid('id_kelas_mk')->constrained('kelas_mk', 'id');
            $table->foreignUuid('id_semester')->constrained('semester', 'id');
            $table->integer('jumlah_jam');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beban_ajar_dosen');
    }
};
