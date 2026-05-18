<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruang_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_ruang', 30)->unique();
            $table->string('nama_ruang');
            $table->string('gedung')->nullable();
            $table->string('lantai', 20)->nullable();
            $table->unsignedInteger('kapasitas')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruang_kuliah');
    }
};
