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
        Schema::create('landing_content', function (Blueprint $table) {
            $table->uuid('id')->primary();

             // Hero
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_background')->nullable();
            
            // Statistik
            $table->integer('jumlah_program_studi')->default(0);
            $table->integer('jumlah_mahasiswa')->default(0);
            $table->integer('jumlah_dosen')->default(0);
            $table->integer('jumlah_mitra')->default(0);

            // Keunggulan
            $table->text('keunggulan')->nullable();

            // Logo dan nama aplikasi
            $table->string('logo')->nullable();
            $table->string('nama_aplikasi')->nullable();

            // Footer
            $table->text('deskripsi_footer')->nullable();
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('youtube')->nullable();
            $table->string('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_content');
    }
};
