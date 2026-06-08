<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_collective_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_batch')->constrained('krs_collective_batches')->cascadeOnDelete();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('id_krs')->nullable()->constrained('krs')->nullOnDelete();
            $table->foreignUuid('id_khs')->nullable()->constrained('khs')->nullOnDelete();
            $table->string('status');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['id_batch', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_collective_batch_items');
    }
};
