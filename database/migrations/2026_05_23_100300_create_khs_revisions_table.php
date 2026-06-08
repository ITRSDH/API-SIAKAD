<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('khs_revisions')) {
            return;
        }

        Schema::create('khs_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_khs')->constrained('khs', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_import_batch')->nullable()->constrained('khs_import_batches', 'id')->nullOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->json('khs_snapshot');
            $table->json('khs_detail_snapshot');
            $table->foreignUuid('created_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('khs_revisions')) {
            return;
        }

        Schema::dropIfExists('khs_revisions');
    }
};
