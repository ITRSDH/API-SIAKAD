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
        Schema::create('indikator_kinerja_cpl', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_cpl')
                ->constrained('cpl')
                ->cascadeOnDelete();
            $table->string('kode_ik_cpl', 255);
            $table->text('deskripsi_ik_cpl_indonesia');
            $table->text('deskripsi_ik_cpl_english')->nullable();
            $table->enum('kategori_ik_cpl', ['S', 'P', 'KU', 'KK']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indikator_kinerja_cpl');
    }
};
