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
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi');
            $table->foreignUuid('id_semester')->constrained('semester');
            $table->string('nama_kurikulum');

            $table->unsignedSmallInteger('jumlah_sks_lulus');
            $table->unsignedSmallInteger('jumlah_sks_wajib');
            $table->unsignedSmallInteger('jumlah_sks_pilihan');

            $table->timestamps();

            $table->unique(['id_prodi', 'nama_kurikulum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurikulum');
    }
};
