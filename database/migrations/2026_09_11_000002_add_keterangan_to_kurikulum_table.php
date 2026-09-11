<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('kurikulum') && ! Schema::hasColumn('kurikulum', 'keterangan')) {
            Schema::table('kurikulum', function (Blueprint $table) {
                $table->text('keterangan')->nullable()->after('is_locked');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kurikulum') && Schema::hasColumn('kurikulum', 'keterangan')) {
            Schema::table('kurikulum', function (Blueprint $table) {
                $table->dropColumn('keterangan');
            });
        }
    }
};
