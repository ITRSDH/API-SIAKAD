<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('khs_detail', 'mutu')) {
            Schema::table('khs_detail', function (Blueprint $table) {
                $table->decimal('mutu', 6, 2)->nullable()->after('bobot_nilai');
            });
        }

        if (!Schema::hasColumn('khs_detail', 'id_import_batch')) {
            Schema::table('khs_detail', function (Blueprint $table) {
                $table->uuid('id_import_batch')->nullable()->after('id_mata_kuliah');
            });

            Schema::table('khs_detail', function (Blueprint $table) {
                $table->foreign('id_import_batch')
                    ->references('id')
                    ->on('khs_import_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('khs_detail', function (Blueprint $table) {
            if (Schema::hasColumn('khs_detail', 'id_import_batch')) {
                $table->dropForeign(['id_import_batch']);
            }
        });

        Schema::table('khs_detail', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('khs_detail', 'mutu')) {
                $columns[] = 'mutu';
            }

            if (Schema::hasColumn('khs_detail', 'id_import_batch')) {
                $columns[] = 'id_import_batch';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
