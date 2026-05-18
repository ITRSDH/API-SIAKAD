<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transkrip_detail', function (Blueprint $table) {
            $table->uuid('id_mata_kuliah')->nullable()->after('id_krs_detail');
            $table->foreign('id_mata_kuliah')
                ->references('id')
                ->on('mata_kuliah')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transkrip_detail', function (Blueprint $table) {
            $table->dropForeign(['id_mata_kuliah']);
            $table->dropColumn('id_mata_kuliah');
        });
    }
};
