<?php

namespace Database\Seeders;

use App\Models\Akademik\NilaiTransfer;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\KonversiMataKuliah;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumMataKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\PeriodeKrs;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class KrsDummyTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $prodi = Prodi::where('kode_prodi', '02')->firstOrFail();
            $semesterAktif = Semester::with('tahunAkademik')->where('status', 'Aktif')->firstOrFail();

            // 1. Pastikan Periode KRS aktif dan terbuka untuk testing
            PeriodeKrs::updateOrCreate(
                ['id_semester' => $semesterAktif->id],
                [
                    'tanggal_mulai' => '2026-01-01',
                    'tanggal_selesai' => '2026-12-31',
                    'status' => 'aktif',
                    'catatan' => 'Periode KRS Aktif Testing Reguler & RPL',
                ]
            );

            // 2. Setup Kurikulum Reguler (BDN26)
            $kurikulumReguler = Kurikulum::firstOrNew(['id' => 'a27d764a-4fd8-4908-b27e-214892fa22b5']);
            $kurikulumReguler->id_prodi = $prodi->id;
            $kurikulumReguler->id_semester = $semesterAktif->id;
            $kurikulumReguler->nama_struktur_mk = 'BDN26';
            $kurikulumReguler->jumlah_sks_lulus = 20;
            $kurikulumReguler->jumlah_sks_wajib = 20;
            $kurikulumReguler->jumlah_sks_pilihan = 0;
            $kurikulumReguler->status = 'Aktif';
            $kurikulumReguler->is_locked = 0;
            $kurikulumReguler->keterangan = 'Kurikulum Reguler Kebidanan Paket Semester 1';
            $kurikulumReguler->save();

            // Buat kelas kuliah (Kelas 1A) untuk setiap mata kuliah di Kurikulum Reguler BDN26
            $kmkReguler = KurikulumMataKuliah::where('id_kurikulum', $kurikulumReguler->id)->get();
            foreach ($kmkReguler as $kmk) {
                KelasKuliah::firstOrCreate(
                    [
                        'id_kurikulum_mata_kuliah' => $kmk->id,
                        'id_semester' => $semesterAktif->id,
                        'nama_kelas' => '1A',
                    ],
                    [
                        'id_prodi' => $prodi->id,
                        'kapasitas_peserta' => 40,
                        'lingkup' => 'internal',
                        'mode_kuliah' => 'offline',
                    ]
                );
            }

            // 3. Setup Kurikulum RPL (BDN26-RPL)
            $kurikulumRpl = Kurikulum::firstOrNew(['nama_struktur_mk' => 'BDN26-RPL', 'id_prodi' => $prodi->id]);
            if (! $kurikulumRpl->exists) {
                $kurikulumRpl->id = (string) Str::uuid();
            }
            $kurikulumRpl->id_semester = $semesterAktif->id;
            $kurikulumRpl->nama_struktur_mk = 'BDN26-RPL';
            $kurikulumRpl->jumlah_sks_lulus = 20;
            $kurikulumRpl->jumlah_sks_wajib = 20;
            $kurikulumRpl->jumlah_sks_pilihan = 0;
            $kurikulumRpl->status = 'Aktif';
            $kurikulumRpl->is_locked = 0;
            $kurikulumRpl->keterangan = 'Kurikulum Khusus RPL / Alih Jenjang D3 ke Profesi/Sarjana Kebidanan (Paket Semester 5)';
            $kurikulumRpl->save();

            // Daftar 7 MK Semester 5 untuk BDN26-RPL
            $sem5Codes = [
                '05.Bd.5.030.2K', // Bahasa Inggris dalam Kebidanan (2 SKS)
                '05.Bd.5.031.3K', // Kewirausahaan dalam Kebidanan (3 SKS)
                '05.Bd.403.2K',   // Kesehatan Masyarakat (2 SKS)
                '05BD5042K',      // ORGANISASI DAN MANAJEMEN PELAYANAN KES (2 SKS)
                '04.Bd.5.026.3K', // Kegawatdaruratan Maternal Neonatal BLS (3 SKS)
                '05BD3074K',      // PRAKTEK KLINIK KEBIDANAN I (RS) (4 SKS)
                '06.Bd.312.3K',   // Praktik Kebidanan Komunitas (3 SKS)
            ];

            $mksSem5 = MataKuliah::whereIn('kode_mk', $sem5Codes)
                ->where('id_prodi', $prodi->id)
                ->get();

            foreach ($mksSem5 as $mk) {
                $kmkRpl = KurikulumMataKuliah::firstOrCreate(
                    [
                        'id_kurikulum' => $kurikulumRpl->id,
                        'id_mata_kuliah' => $mk->id,
                    ],
                    [
                        'semester_ke' => 5,
                        'status_mk' => 'wajib',
                        'is_wajib' => 1,
                    ]
                );

                // Buat kelas kuliah (Kelas 5-RPL)
                KelasKuliah::firstOrCreate(
                    [
                        'id_kurikulum_mata_kuliah' => $kmkRpl->id,
                        'id_semester' => $semesterAktif->id,
                        'nama_kelas' => '5-RPL',
                    ],
                    [
                        'id_prodi' => $prodi->id,
                        'kapasitas_peserta' => 35,
                        'lingkup' => 'internal',
                        'mode_kuliah' => 'offline',
                    ]
                );
            }

            // 4. Setup Konversi Mata Kuliah (Rule Penyetaraan BDN26 -> BDN26-RPL)
            $mkPsikologi = MataKuliah::where('kode_mk', '01.Bd.5.036.2K')->first();
            if ($mkPsikologi) {
                KonversiMataKuliah::firstOrCreate(
                    [
                        'id_kurikulum_asal' => $kurikulumReguler->id,
                        'id_kurikulum_tujuan' => $kurikulumRpl->id,
                        'id_mata_kuliah_asal' => $mkPsikologi->id,
                        'id_mata_kuliah_tujuan' => $mkPsikologi->id,
                    ],
                    [
                        'status_konversi' => KonversiMataKuliah::STATUS_DIAKUI,
                        'min_bobot_nilai' => 3.00,
                        'catatan' => 'Penyetaraan otomatis MK Dasar Kebidanan untuk alih jenjang RPL',
                    ]
                );
            }

            // 5. Test Student 1: Mahasiswa Reguler (0225001)
            $userReguler = User::firstOrNew(['email' => '0225001@stikes.ac.id']);
            $userReguler->name = 'Siti Rahmawati (Reguler)';
            $userReguler->password = Hash::make('password123');
            $userReguler->status = 'aktif';
            $userReguler->save();
            $userReguler->syncRoles(['mahasiswa']);

            $mhsReguler = Mahasiswa::firstOrNew(['nim' => '0225001']);
            $mhsReguler->id_prodi = $prodi->id;
            $mhsReguler->user_id = $userReguler->id;
            $mhsReguler->nama_mahasiswa = 'Siti Rahmawati (Reguler)';
            $mhsReguler->nik = '3201019901010001';
            $mhsReguler->angkatan = 2025;
            $mhsReguler->jenis_pendaftaran = 'Reguler';
            $mhsReguler->jalur_masuk = 'Reguler';
            $mhsReguler->status = 'Aktif';
            $mhsReguler->sks_diakui = 0;
            $mhsReguler->save();

            // 6. Test Student 2: Mahasiswa RPL (0225001B)
            $userRpl = User::firstOrNew(['email' => '0225001b@stikes.ac.id']);
            $userRpl->name = 'Dewi Lestari, A.Md.Keb (RPL)';
            $userRpl->password = Hash::make('password123');
            $userRpl->status = 'aktif';
            $userRpl->save();
            $userRpl->syncRoles(['mahasiswa']);

            $mhsRpl = Mahasiswa::firstOrNew(['nim' => '0225001B']);
            $mhsRpl->id_prodi = $prodi->id;
            $mhsRpl->user_id = $userRpl->id;
            $mhsRpl->nama_mahasiswa = 'Dewi Lestari, A.Md.Keb (RPL)';
            $mhsRpl->nik = '3201019901010002';
            $mhsRpl->angkatan = 2025;
            $mhsRpl->jenis_pendaftaran = 'RPL';
            $mhsRpl->jalur_masuk = 'RPL';
            $mhsRpl->status = 'Aktif';
            $mhsRpl->sks_diakui = 80;
            $mhsRpl->perguruan_tinggi_asal = 'Akademi Kebidanan Bethesda';
            $mhsRpl->prodi_asal = 'D3 Kebidanan';
            $mhsRpl->save();

            // Isi 80 SKS Nilai Transfer D3 Asal (excluding Semester 5 package courses)
            $sem5Ids = $mksSem5->pluck('id')->toArray();
            $transferMks = MataKuliah::where('id_prodi', $prodi->id)
                ->whereNotIn('id', $sem5Ids)
                ->orderBy('kode_mk')
                ->get();

            $accumulatedSks = 0;
            NilaiTransfer::where('id_mahasiswa', $mhsRpl->id)->delete();

            foreach ($transferMks as $index => $mk) {
                if ($accumulatedSks + $mk->sks <= 80) {
                    $accumulatedSks += $mk->sks;
                    $isA = ($index % 2 === 0);

                    NilaiTransfer::create([
                        'id_mahasiswa' => $mhsRpl->id,
                        'id_mata_kuliah' => $mk->id,
                        'kode_mata_kuliah_asal' => 'D3-'.$mk->kode_mk,
                        'nama_mata_kuliah_asal' => $mk->nama_mk.' (Asal D3)',
                        'sks_asal' => $mk->sks,
                        'nilai_huruf_asal' => $isA ? 'A' : 'B',
                        'sks_diakui' => $mk->sks,
                        'nilai_angka_diakui' => $isA ? 85.00 : 75.00,
                        'nilai_huruf_diakui' => $isA ? 'A' : 'B',
                        'nilai_indeks_diakui' => $isA ? 4.00 : 3.00,
                        'keterangan' => 'Hasil Rekognisi Pembelajaran Lampau (RPL) D3 Kebidanan',
                    ]);
                }

                if ($accumulatedSks >= 80) {
                    break;
                }
            }
        });
    }
}
