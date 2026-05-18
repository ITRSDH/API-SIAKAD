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
        Schema::create('cpl_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('id_cpl')
                ->constrained('cpl')
                ->cascadeOnDelete();

            $table->foreignUuid('id_mata_kuliah')
                ->constrained('mata_kuliah')
                ->cascadeOnDelete();

            // Bobot kontribusi MK terhadap CPL
            $table->decimal('bobot', 5, 2)->default(0)
                ->comment('Bobot kontribusi 0–100');

            $table->timestamps();

            // Hindari relasi ganda
            $table->unique(['id_cpl', 'id_mata_kuliah'], 'cpl_mk_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpl_mk');
    }
};
