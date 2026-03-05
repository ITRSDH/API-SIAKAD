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
        Schema::create('profile_lulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('kode_pl', 100)->unique();
            $table->string('profile_lulusan', 255);
            $table->text('deskripsi_profile_lulusan_indonesia');
            $table->text('deskripsi_profile_lulusan_english')->nullable();
            $table->string('profesi_lulusan', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_lulusan');
    }
};
