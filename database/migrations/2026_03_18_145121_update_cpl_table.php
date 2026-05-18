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
        Schema::table('cpl', function (Blueprint $table) {
            $table->dropColumn('cpl');

            $table->string('kategori_cpl')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cpl', function (Blueprint $table) {
            $table->string('cpl')->nullable();

            $table->string('kategori_cpl')
                ->nullable(false)
                ->change();
        });
    }
};
