<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['id_kurikulum']);
            $table->dropColumn('id_kurikulum');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->uuid('id_kurikulum')->nullable()->after('id_prodi');
            $table->foreign('id_kurikulum')
                ->references('id')
                ->on('kurikulum')
                ->nullOnDelete();
        });
    }
};
