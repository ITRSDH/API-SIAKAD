<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('krs_detail', 'id_mata_kuliah')) {
                $table->uuid('id_mata_kuliah')->nullable()->after('id_kelas_kuliah');
            }

            if (!Schema::hasColumn('krs_detail', 'mutu')) {
                $table->decimal('mutu', 6, 2)->nullable()->after('bobot_nilai');
            }
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('krs_detail', 'id_mata_kuliah')) {
                return;
            }

            try {
                $table->foreign('id_mata_kuliah')
                    ->references('id')
                    ->on('mata_kuliah')
                    ->nullOnDelete();
            } catch (\Throwable $exception) {
                // Ignore if foreign key already exists.
            }
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            $table->decimal('bobot_nilai', 6, 2)->nullable()->change();
        });

        DB::statement("
            UPDATE krs_detail kd
            JOIN kelas_kuliah kk ON kk.id = kd.id_kelas_kuliah
            JOIN kurikulum_mata_kuliah kmk ON kmk.id = kk.id_kurikulum_mata_kuliah
            SET kd.id_mata_kuliah = kmk.id_mata_kuliah
            WHERE kd.id_mata_kuliah IS NULL
        ");

        DB::statement("
            UPDATE krs_detail kd
            JOIN kelas_kuliah kk ON kk.id = kd.id_kelas_kuliah
            JOIN kurikulum_mata_kuliah kmk ON kmk.id = kk.id_kurikulum_mata_kuliah
            JOIN mata_kuliah mk ON mk.id = kmk.id_mata_kuliah
            SET
                kd.mutu = COALESCE(kd.mutu, kd.bobot_nilai),
                kd.bobot_nilai = CASE
                    WHEN kd.bobot_nilai IS NULL THEN NULL
                    WHEN kd.mutu IS NOT NULL THEN ROUND(mk.sks * kd.mutu, 2)
                    WHEN kd.bobot_nilai <= 4 THEN ROUND(mk.sks * kd.bobot_nilai, 2)
                    ELSE kd.bobot_nilai
                END
            WHERE kd.id_kelas_kuliah IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE krs_detail
            SET bobot_nilai = mutu
            WHERE mutu IS NOT NULL
        ");

        Schema::table('krs_detail', function (Blueprint $table) {
            $table->decimal('bobot_nilai', 3, 2)->nullable()->change();
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            if (Schema::hasColumn('krs_detail', 'id_mata_kuliah')) {
                try {
                    $table->dropForeign(['id_mata_kuliah']);
                } catch (\Throwable $exception) {
                    // Ignore if foreign key does not exist.
                }
            }
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('krs_detail', 'mutu')) {
                $columns[] = 'mutu';
            }

            if (Schema::hasColumn('krs_detail', 'id_mata_kuliah')) {
                $columns[] = 'id_mata_kuliah';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
