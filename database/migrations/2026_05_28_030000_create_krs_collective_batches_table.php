<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_collective_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('id_tahun_akademik')->nullable()->constrained('tahun_akademik')->nullOnDelete();
            $table->foreignUuid('id_semester')->constrained('semester')->cascadeOnDelete();
            $table->string('context_type')->default('historical_study');
            $table->string('action_type');
            $table->json('filters')->nullable();
            $table->json('payload')->nullable();
            $table->json('summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'action_type']);
            $table->index(['id_semester', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_collective_batches');
    }
};
