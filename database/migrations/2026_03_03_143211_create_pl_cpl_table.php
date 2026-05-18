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
        Schema::create('pl_cpl', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('id_profile_lulusan')
                ->constrained('profile_lulusan')
                ->cascadeOnDelete();

            $table->foreignUuid('id_cpl')
                ->constrained('cpl')
                ->cascadeOnDelete();

            // Bobot 0–100 (bisa desimal)
            $table->decimal('bobot', 5, 2)->default(0)
                ->comment('Bobot kontribusi 0–100');

            $table->timestamps();

            // Hindari duplikasi relasi
            $table->unique(['id_profile_lulusan', 'id_cpl'], 'pl_cpl_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pl_cpl');
    }
};
