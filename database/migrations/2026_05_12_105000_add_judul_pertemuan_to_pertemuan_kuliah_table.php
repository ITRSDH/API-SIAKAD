<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuan_kuliah', function (Blueprint $table) {
            $table->string('judul_pertemuan')->nullable()->after('pertemuan_ke');
        });
    }

    public function down(): void
    {
        Schema::table('pertemuan_kuliah', function (Blueprint $table) {
            $table->dropColumn('judul_pertemuan');
        });
    }
};
