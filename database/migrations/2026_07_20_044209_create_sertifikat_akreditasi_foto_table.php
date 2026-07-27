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
        Schema::create('sertifikat_akreditasi_foto', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('sertifikat_akreditasi_id');

            $table->string('foto');

            $table->timestamps();

            $table->foreign('sertifikat_akreditasi_id')
                ->references('id')
                ->on('sertifikat_akreditasi')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sertifikat_akreditasi_foto');
    }
};