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
        Schema::table('prestasi', function (Blueprint $table) {
            // Tambah kolom id_prodi sebagai foreign key
            $table->uuid('id_prodi')->nullable()->after('id');
            
            // Tambah foreign key constraint
            $table->foreign('id_prodi')
                ->references('id')
                ->on('prodi')
                ->onDelete('set null');
            $table->dropColumn('program_studi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestasi', function (Blueprint $table) {
            $table->dropForeign(['id_prodi']);
            $table->dropColumn('id_prodi');
            $table->string('program_studi')->after('nama_mahasiswa');
        });
    }
};
