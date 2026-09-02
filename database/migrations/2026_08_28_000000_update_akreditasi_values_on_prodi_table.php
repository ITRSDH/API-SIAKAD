<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lebarkan kolom akreditasi lalu ubah nilainya:
     * A -> Unggul, B -> Baik Sekali, C -> Terakreditasi Pertama.
     */
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->string('akreditasi', 50)->nullable()->change();
        });

        $mapping = [
            'A' => 'Unggul',
            'B' => 'Baik Sekali',
            'C' => 'Terakreditasi Pertama',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('prodi')
                ->where('akreditasi', $old)
                ->update(['akreditasi' => $new]);
        }
    }

    /**
     * Kembalikan nilai ke bentuk lama (A/B/C) lalu kecilkan kolom lagi.
     */
    public function down(): void
    {
        $mapping = [
            'Unggul' => 'A',
            'Baik Sekali' => 'B',
            'Terakreditasi Pertama' => 'C',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('prodi')
                ->where('akreditasi', $old)
                ->update(['akreditasi' => $new]);
        }

        Schema::table('prodi', function (Blueprint $table) {
            $table->string('akreditasi', 10)->nullable()->change();
        });
    }
};
