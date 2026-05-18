<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kuliah', function (Blueprint $table) {
            $table->uuid('id_ruang')->nullable()->after('id_kelas_kuliah');
            $table->foreign('id_ruang')
                ->references('id')
                ->on('ruang_kuliah')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kuliah', function (Blueprint $table) {
            $table->dropForeign(['id_ruang']);
            $table->dropColumn('id_ruang');
        });
    }
};
