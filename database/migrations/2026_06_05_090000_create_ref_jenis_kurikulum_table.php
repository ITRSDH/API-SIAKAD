<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_jenis_kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_jenis', 20)->unique();
            $table->string('nama_jenis_kurikulum', 150);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_jenis_kurikulum');
    }
};
