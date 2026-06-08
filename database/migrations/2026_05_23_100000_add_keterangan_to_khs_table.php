<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('khs', 'keterangan')) {
            return;
        }

        Schema::table('khs', function (Blueprint $table) {
            $table->string('keterangan')->nullable()->after('ipk');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('khs', 'keterangan')) {
            return;
        }

        Schema::table('khs', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
