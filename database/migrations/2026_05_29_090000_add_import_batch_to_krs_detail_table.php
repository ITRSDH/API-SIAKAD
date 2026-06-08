<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('krs_detail', 'id_import_batch')) {
            Schema::table('krs_detail', function (Blueprint $table) {
                $table->uuid('id_import_batch')->nullable()->after('id_mata_kuliah');
            });
        }

        Schema::table('krs_detail', function (Blueprint $table) {
            try {
                $table->foreign('id_import_batch')
                    ->references('id')
                    ->on('khs_import_batches')
                    ->nullOnDelete();
            } catch (\Throwable $exception) {
                // Ignore if foreign key already exists.
            }
        });
    }

    public function down(): void
    {
        Schema::table('krs_detail', function (Blueprint $table) {
            if (Schema::hasColumn('krs_detail', 'id_import_batch')) {
                try {
                    $table->dropForeign(['id_import_batch']);
                } catch (\Throwable $exception) {
                    // Ignore if foreign key does not exist.
                }
            }
        });

        if (Schema::hasColumn('krs_detail', 'id_import_batch')) {
            Schema::table('krs_detail', function (Blueprint $table) {
                $table->dropColumn('id_import_batch');
            });
        }
    }
};
