<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuan_kuliah', function (Blueprint $table) {
            $table->enum('status', ['draft', 'terjadwal', 'selesai', 'dibatalkan'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pertemuan_kuliah', function (Blueprint $table) {
            $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft')->change();
        });
    }
};
