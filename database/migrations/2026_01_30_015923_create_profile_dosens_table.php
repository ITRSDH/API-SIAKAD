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
        Schema::create('profile_dosen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 100);
            $table->string('nidn', 100);
            $table->string('foto')->nullable();
            $table->string('status', 100);
            $table->uuid('id_prodi')->nullable();
            $table->text('biografi')->nullable();
            
            // Tambah foreign key constraint
            $table->foreign('id_prodi')
                ->references('id')
                ->on('prodi')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_dosen');
    }
};
