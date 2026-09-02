<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom kurikulum.nama_kurikulum diubah menjadi nama_struktur_mk
        // karena fungsi tabel kurikulum adalah struktur mata kuliah (bukan
        // nama kurikulum formal). Konsep "kurikulum induk" sudah dihapus;
        // tabel kurikulum kini satu-satunya sumber struktur kurikulum.
        Schema::table('kurikulum', function (Blueprint $table) {
            $table->renameColumn('nama_kurikulum', 'nama_struktur_mk');
        });
    }

    public function down(): void
    {
        Schema::table('kurikulum', function (Blueprint $table) {
            $table->renameColumn('nama_struktur_mk', 'nama_kurikulum');
        });
    }
};
