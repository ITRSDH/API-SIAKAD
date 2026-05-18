<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs', function (Blueprint $table) {
            if (!$this->foreignKeyExists('krs', 'krs_id_mahasiswa_foreign')) {
                $table->foreign('id_mahasiswa')->references('id')->on('mahasiswa')->onDelete('cascade');
            }

            if (!$this->foreignKeyExists('krs', 'krs_id_semester_foreign')) {
                $table->foreign('id_semester')->references('id')->on('semester')->onDelete('cascade');
            }

            if (!$this->foreignKeyExists('krs', 'krs_approved_by_foreign')) {
                $table->foreign('approved_by')->references('id')->on('dosen')->onDelete('set null');
            }
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            if (!$this->foreignKeyExists('krs_detail', 'krs_detail_id_krs_foreign')) {
                $table->foreign('id_krs')->references('id')->on('krs')->onDelete('cascade');
            }

            if (!$this->foreignKeyExists('krs_detail', 'krs_detail_id_kelas_kuliah_foreign')) {
                $table->foreign('id_kelas_kuliah')->references('id')->on('kelas_kuliah')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('krs_detail', function (Blueprint $table) {
            if ($this->foreignKeyExists('krs_detail', 'krs_detail_id_krs_foreign')) {
                $table->dropForeign('krs_detail_id_krs_foreign');
            }

            if ($this->foreignKeyExists('krs_detail', 'krs_detail_id_kelas_kuliah_foreign')) {
                $table->dropForeign('krs_detail_id_kelas_kuliah_foreign');
            }
        });

        Schema::table('krs', function (Blueprint $table) {
            if ($this->foreignKeyExists('krs', 'krs_id_mahasiswa_foreign')) {
                $table->dropForeign('krs_id_mahasiswa_foreign');
            }

            if ($this->foreignKeyExists('krs', 'krs_id_semester_foreign')) {
                $table->dropForeign('krs_id_semester_foreign');
            }

            if ($this->foreignKeyExists('krs', 'krs_approved_by_foreign')) {
                $table->dropForeign('krs_approved_by_foreign');
            }
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $result = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
                [$database, $table, $constraintName, 'FOREIGN KEY']
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(sprintf('PRAGMA foreign_key_list(%s)', $table));

            return collect($rows)->contains(function ($row) use ($constraintName) {
                return ($row->id ?? null) === $constraintName || ($row->from ?? null) === $constraintName;
            });
        }

        return false;
    }
};
