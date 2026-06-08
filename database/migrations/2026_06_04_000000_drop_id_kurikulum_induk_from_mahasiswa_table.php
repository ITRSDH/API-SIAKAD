<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['id_kurikulum_induk']);
            $table->dropColumn('id_kurikulum_induk');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->foreignUuid('id_kurikulum_induk')
                ->nullable()
                ->after('id_kurikulum')
                ->constrained('kurikulum_induk')
                ->nullOnDelete();
        });

        DB::statement('
            UPDATE mahasiswa
            INNER JOIN kurikulum ON kurikulum.id = mahasiswa.id_kurikulum
            SET mahasiswa.id_kurikulum_induk = kurikulum.id_kurikulum_induk
            WHERE mahasiswa.id_kurikulum IS NOT NULL
        ');
    }
};
