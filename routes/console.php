<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mahasiswa:sync-rpl-nim', function () {
    $this->info('Memulai sinkronisasi klasifikasi Mahasiswa Reguler vs RPL berdasarkan NIM...');

    // 1. Mahasiswa berakhiran 'B' atau 'b' (Standar RPL STIKES Dian Husada)
    \Illuminate\Support\Facades\DB::table('mahasiswa')
        ->where(function ($query) {
            $query->where('nim', 'LIKE', '%B')
                ->orWhere('nim', 'LIKE', '%b');
        })
        ->update([
            'jenis_pendaftaran' => 'RPL',
            'jalur_masuk' => \Illuminate\Support\Facades\DB::raw("CASE WHEN jalur_masuk IS NULL OR jalur_masuk = '' OR jalur_masuk = 'Reguler' THEN 'RPL' ELSE jalur_masuk END"),
        ]);

    // 2. Mahasiswa tanpa akhiran 'B'/'b'
    \Illuminate\Support\Facades\DB::table('mahasiswa')
        ->where('nim', 'NOT LIKE', '%B')
        ->where('nim', 'NOT LIKE', '%b')
        ->where(function ($query) {
            $query->whereNull('jenis_pendaftaran')
                ->orWhere('jenis_pendaftaran', '')
                ->orWhere('jenis_pendaftaran', 'Reguler');
        })
        ->update([
            'jenis_pendaftaran' => 'Reguler',
            'jalur_masuk' => \Illuminate\Support\Facades\DB::raw("CASE WHEN jalur_masuk IS NULL OR jalur_masuk = '' THEN 'Reguler' ELSE jalur_masuk END"),
        ]);

    $totalRpl = \Illuminate\Support\Facades\DB::table('mahasiswa')->where('jenis_pendaftaran', 'RPL')->count();
    $totalReguler = \Illuminate\Support\Facades\DB::table('mahasiswa')->where('jenis_pendaftaran', 'Reguler')->count();

    $this->info('✓ Sinkronisasi selesai:');
    $this->line("  - Mahasiswa RPL (NIM akhiran 'B'): {$totalRpl}");
    $this->line("  - Mahasiswa Reguler: {$totalReguler}");
})->purpose('Sinkronisasi otomatis status jenis pendaftaran mahasiswa (RPL vs Reguler) berdasarkan akhiran NIM');
