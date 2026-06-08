<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('khs_import_batches')) {
            return;
        }

        Schema::create('khs_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_semester')->constrained('semester', 'id')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users', 'id')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->enum('status', ['uploaded', 'previewed', 'processed', 'failed', 'rolled_back'])->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('total_success')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('khs_import_batches')) {
            return;
        }

        Schema::dropIfExists('khs_import_batches');
    }
};
