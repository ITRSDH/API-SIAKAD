<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riwayat_kurikulum_mahasiswa', function (Blueprint $table) {
            $table->foreignUuid('id_kurikulum_induk')
                ->nullable()
                ->after('id_kurikulum')
                ->constrained('kurikulum_induk')
                ->nullOnDelete();
        });

        DB::statement('
            UPDATE riwayat_kurikulum_mahasiswa rkm
            INNER JOIN kurikulum k ON k.id = rkm.id_kurikulum
            SET rkm.id_kurikulum_induk = k.id_kurikulum_induk
            WHERE rkm.id_kurikulum IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('riwayat_kurikulum_mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['id_kurikulum_induk']);
            $table->dropColumn('id_kurikulum_induk');
        });
    }
};
