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
        Schema::create('cpl', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('kode_cpl', 100)->unique();
            // $table->string('cpl', 255);
            $table->text('deskripsi_cpl_indonesia');
            $table->text('deskripsi_cpl_english')->nullable();
            $table->enum('kategori_cpl', ['S', 'P', 'KU', 'KK'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpl');
    }
};
