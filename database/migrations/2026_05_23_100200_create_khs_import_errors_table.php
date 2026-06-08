<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('khs_import_errors')) {
            return;
        }

        Schema::create('khs_import_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_import_batch')->constrained('khs_import_batches', 'id')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('nim')->nullable();
            $table->string('kode_mk')->nullable();
            $table->string('error_type');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('khs_import_errors')) {
            return;
        }

        Schema::dropIfExists('khs_import_errors');
    }
};
