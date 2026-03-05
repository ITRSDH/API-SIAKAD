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
        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kurikulum')
                ->constrained('kurikulum')
                ->cascadeOnDelete();

            $table->foreignUuid('id_mata_kuliah')
                ->nullable()
                ->constrained('mata_kuliah')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('semester_ke')->nullable();
            $table->enum('status_mk', ['wajib', 'pilihan'])->nullable();

            $table->boolean('is_wajib')->default(true)->nullable();
            $table->timestamps();

            $table->unique(['id_kurikulum', 'id_mata_kuliah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurikulum_mata_kuliah');
    }
};
