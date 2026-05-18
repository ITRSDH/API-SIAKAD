<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_kuliah_prasyarat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mata_kuliah')->constrained('mata_kuliah', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_mata_kuliah_prasyarat')->constrained('mata_kuliah', 'id')->restrictOnDelete();
            $table->decimal('min_bobot_nilai', 3, 2)->default(2.00);
            $table->timestamps();

            $table->unique(['id_mata_kuliah', 'id_mata_kuliah_prasyarat'], 'mk_prasyarat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_kuliah_prasyarat');
    }
};
