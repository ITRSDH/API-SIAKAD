<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mahasiswa') || ! Schema::hasColumn('mahasiswa', 'jenis_pendaftaran')) {
            return;
        }

        // 1. Deteksi semua mahasiswa dengan akhiran NIM 'B' atau 'b' (Standar RPL STIKES Dian Husada)
        // dan perbarui jenis_pendaftaran = 'RPL' serta jalur_masuk = 'RPL'
        DB::table('mahasiswa')
            ->where(function ($query) {
                $query->where('nim', 'LIKE', '%B')
                    ->orWhere('nim', 'LIKE', '%b');
            })
            ->update([
                'jenis_pendaftaran' => 'RPL',
                'jalur_masuk' => DB::raw("CASE WHEN jalur_masuk IS NULL OR jalur_masuk = '' OR jalur_masuk = 'Reguler' THEN 'RPL' ELSE jalur_masuk END"),
            ]);

        // 2. Mahasiswa tanpa akhiran 'B'/'b' yang belum memiliki jenis pendaftaran atau kosong
        // dipastikan berstatus 'Reguler'
        DB::table('mahasiswa')
            ->where('nim', 'NOT LIKE', '%B')
            ->where('nim', 'NOT LIKE', '%b')
            ->where(function ($query) {
                $query->whereNull('jenis_pendaftaran')
                    ->orWhere('jenis_pendaftaran', '')
                    ->orWhere('jenis_pendaftaran', 'Reguler');
            })
            ->update([
                'jenis_pendaftaran' => 'Reguler',
                'jalur_masuk' => DB::raw("CASE WHEN jalur_masuk IS NULL OR jalur_masuk = '' THEN 'Reguler' ELSE jalur_masuk END"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback untuk menjaga integritas data riil mahasiswa
    }
};
