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

Artisan::command('siakad:audit-kelas-kurikulum {--semester= : ID Semester atau Nama Semester} {--prodi= : ID Program Studi} {--fix : Sinkronkan mata kuliah krs_detail ke kurikulum kelas}', function () {
    $this->info('=== AUDIT KELAS KULIAH & KESESUAIAN KURIKULUM PESERTA ===');

    $semesterInput = $this->option('semester');
    $prodiInput = $this->option('prodi');
    $isFix = $this->option('fix');

    // Tentukan Semester
    $semesterQuery = \App\Models\MasterData\Semester::query();
    if ($semesterInput) {
        $semesterQuery->where(function ($q) use ($semesterInput) {
            $q->where('id', $semesterInput)
                ->orWhere('kode_semester', 'LIKE', "%{$semesterInput}%")
                ->orWhere('nama_semester', 'LIKE', "%{$semesterInput}%");
        });
    } else {
        // Ambil semester aktif atau semester paling akhir
        $semesterQuery->whereRaw('LOWER(status) = ?', ['aktif']);
    }
    $targetSemester = $semesterQuery->first();

    if (! $targetSemester) {
        $targetSemester = \App\Models\MasterData\Semester::latest()->first();
    }

    if (! $targetSemester) {
        $this->error('Data Semester tidak ditemukan.');

        return 1;
    }

    $this->line("Semester Analisis: <comment>{$targetSemester->kode_semester} ({$targetSemester->nama_semester})</comment>");

    $kelasQuery = \App\Models\MasterData\KelasKuliah::with([
        'prodi',
        'kurikulumMataKuliah.mataKuliah',
        'kurikulumMataKuliah.kurikulum',
        'krsDetail.krs.mahasiswa',
    ])->where('id_semester', $targetSemester->id);

    if ($prodiInput) {
        $kelasQuery->where('id_prodi', $prodiInput);
    }

    $kelasList = $kelasQuery->get();
    $this->line("Total Kelas dianalisis: <comment>{$kelasList->count()}</comment>\n");

    if ($kelasList->isEmpty()) {
        $this->warn('Tidak ada kelas kuliah yang ditemukan pada semester ini.');

        return 0;
    }

    $tableRows = [];
    $mismatchClasses = [];
    $no = 1;

    foreach ($kelasList as $kelas) {
        $prodiName = $kelas->prodi?->nama_prodi ?? '-';
        $kmk = $kelas->kurikulumMataKuliah;
        $mk = $kmk?->mataKuliah;
        $kurikulum = $kmk?->kurikulum;
        $kurikulumName = $kurikulum?->nama_struktur_mk ?? '-';
        $mkTargetId = $kmk?->id_mata_kuliah;

        $isKurikulumRpl = stripos($kurikulumName, 'RPL') !== false;

        $krsDetails = $kelas->krsDetail;
        $totalPeserta = $krsDetails->count();
        $regulerCount = 0;
        $rplCount = 0;
        $krsMismatchCount = 0;

        foreach ($krsDetails as $detail) {
            $mhs = $detail->krs?->mahasiswa;
            $isMhsRpl = ($mhs?->jenis_pendaftaran === 'RPL') || (preg_match('/[Bb]$/', (string) $mhs?->nim) === 1);
            if ($isMhsRpl) {
                $rplCount++;
            } else {
                $regulerCount++;
            }

            if ($mkTargetId && $detail->id_mata_kuliah !== $mkTargetId) {
                $krsMismatchCount++;
            }
        }

        $issues = [];
        if ($totalPeserta > 0 && $isKurikulumRpl && $regulerCount > $rplCount) {
            $issues[] = 'Peserta mayoritas Reguler pada Kurikulum RPL';
        } elseif ($totalPeserta > 0 && ! $isKurikulumRpl && $rplCount > $regulerCount && $rplCount > 0) {
            $issues[] = 'Peserta mayoritas RPL pada Kurikulum Reguler';
        }

        if ($krsMismatchCount > 0) {
            $issues[] = "{$krsMismatchCount} KRS beda ID MK";
        }

        $statusText = empty($issues) ? '<info>OK</info>' : '<fg=yellow>'.implode('; ', $issues).'</>';
        $needsSync = $krsMismatchCount > 0 ? 'YA' : 'TIDAK';

        if (! empty($issues) || $krsMismatchCount > 0) {
            $mismatchClasses[] = [
                'kelas' => $kelas,
                'target_kmk' => $kmk,
                'krs_details' => $krsDetails,
                'mismatch_count' => $krsMismatchCount,
            ];
        }

        $tableRows[] = [
            $no++,
            \Illuminate\Support\Str::limit($prodiName, 18),
            $kelas->nama_kelas,
            \Illuminate\Support\Str::limit($mk?->nama_mk ?? '-', 22),
            \Illuminate\Support\Str::limit($kurikulumName, 25),
            "{$totalPeserta} ({$regulerCount} Reg / {$rplCount} RPL)",
            $statusText,
            $needsSync,
        ];
    }

    $this->table(
        ['#', 'Prodi', 'Kelas', 'Mata Kuliah', 'Kurikulum Kelas', 'Peserta (Reg/RPL)', 'Analisis Status', 'Perlu Sync?'],
        $tableRows
    );

    $totalIssues = count($mismatchClasses);
    $this->line("\nHasil Evaluasi: <comment>{$totalIssues}</comment> kelas terindikasi memiliki ketidaksesuaian kurikulum / KRS peserta.");

    if ($totalIssues > 0 && $isFix) {
        if ($this->confirm('Lanjutkan sinkronisasi mata kuliah pada KRS peserta untuk kelas-kelas di atas?', true)) {
            $fixedPeserta = 0;
            $fixedKelas = 0;

            foreach ($mismatchClasses as $item) {
                $k = $item['kelas'];
                $kmk = $item['target_kmk'];
                if (! $kmk || ! $kmk->id_mata_kuliah) {
                    continue;
                }

                $krsIds = [];
                foreach ($item['krs_details'] as $detail) {
                    if ($detail->id_mata_kuliah !== $kmk->id_mata_kuliah) {
                        $detail->id_mata_kuliah = $kmk->id_mata_kuliah;
                        $detail->save();
                        $fixedPeserta++;
                    }
                    if ($detail->id_krs) {
                        $krsIds[] = $detail->id_krs;
                    }
                }

                foreach (array_unique($krsIds) as $krsId) {
                    $krs = \App\Models\Akademik\KRS::find($krsId);
                    if ($krs) {
                        $krs->update(['total_sks' => $krs->calculateTotalSks()]);
                    }
                }
                $fixedKelas++;
            }

            $this->info("✓ Sukses: {$fixedPeserta} peserta pada {$fixedKelas} kelas berhasil disinkronkan ke kurikulum kelas.");
        }
    } elseif ($totalIssues > 0) {
        $this->line("\n<comment>Tip:</comment> Untuk menyinkronkan data KRS peserta secara massal, jalankan:");
        $this->line('  <info>php artisan siakad:audit-kelas-kurikulum --fix</info>');
        $this->line("Atau buka menu Detail Kelas Kuliah di web dan klik tombol <info>'Sinkronkan Kurikulum Peserta'</info>.");
    }

    return 0;
})->purpose('Audit dan sinkronisasi ketidaksesuaian kurikulum kelas kuliah dengan peserta (Reguler vs RPL)');
