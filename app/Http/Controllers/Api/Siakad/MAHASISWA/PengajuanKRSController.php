<?php

namespace App\Http\Controllers\Api\Siakad\MAHASISWA;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Krs;
use App\Models\MasterData\KrsDetail;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\DosenKelasMk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PengajuanKRSController extends Controller
{
    /**
     * Menampilkan daftar mata kuliah pilihan yang tersedia untuk semester aktif
     * Mengambil data dari KelasMk sebagai sumber utama
     * Menggunakan relasi dengan DosenKelasMk untuk informasi dosen
     */
    public function daftarMatkulPilihan(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            if (!$mahasiswa->id_prodi) {
                return response()->json(['success' => false, 'message' => 'Mahasiswa belum terdaftar di program studi.'], 404);
            }

            // Periksa apakah mahasiswa memiliki kelas pararel
            if (!$mahasiswa->id_kelas_pararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum terdaftar di kelas pararel.',
                    'data' => [
                        'semester_aktif' => null,
                        'kurikulum' => null,
                        'jumlah_matkul_tersedia' => 0,
                        'matkul_pilihan' => []
                    ]
                ], 404);
            }

            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            $isSemesterGanjil = in_array(strtolower(trim($semesterAktif->nama_semester)), ['ganjil', 'odd', '1']);

            // Ambil kurikulum aktif berdasarkan prodi mahasiswa
            $kurikulum = Kurikulum::select('id', 'id_prodi', 'nama_kurikulum')
                ->where('status', true)
                ->where('id_prodi', $mahasiswa->id_prodi)
                ->first();

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum aktif tidak ditemukan.',
                    'debug' => [
                        'id_prodi' => $mahasiswa->id_prodi,
                        'keterangan' => 'Pastikan kurikulum untuk prodi ini memiliki status = true'
                    ]
                ], 404);
            }

            $krsAktif = Krs::where('id_mahasiswa', $mahasiswa->id)
                ->where('id_semester', $semesterAktif->id)
                ->latest()
                ->first();

            $krsStatus = $krsAktif?->status;

            // KRS dikunci jika sudah diajukan
            $isLocked = in_array($krsStatus, [
                'Menunggu Verifikasi',
                'Disetujui'
            ]);

            // Ambil data dari KelasMk sebagai sumber utama
            // Filter hanya kelas yang sesuai dengan kelas pararel mahasiswa
            $kelasMk = KelasMk::where('id_semester', $semesterAktif->id)
                ->where('id_kelas_pararel', $mahasiswa->id_kelas_pararel) // Filter berdasarkan kelas pararel mahasiswa
                ->whereHas('mataKuliah', function ($query) use ($kurikulum, $isSemesterGanjil) {
                    $query->where('id_kurikulum', $kurikulum->id)
                        ->where(function ($q) use ($isSemesterGanjil) {
                            if ($isSemesterGanjil) {
                                $q->whereRaw('semester_rekomendasi % 2 != 0');
                            } else {
                                $q->whereRaw('semester_rekomendasi % 2 = 0');
                            }
                        });
                })
                ->with([
                    'mataKuliah:id,kode_mk,nama_mk,sks,semester_rekomendasi,id_kurikulum',
                    'kelasPararel:id,nama_kelas,id_prodi',
                    'jenisKelas:id,nama_kelas',
                    'dosenKelasMk:id,id_kelas_mk,id_dosen',
                    'dosenKelasMk.dosen:id,nama_dosen,nup'
                ])
                ->get();

            if ($kelasMk->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tidak ada mata kuliah yang tersedia untuk kelas pararel Anda di semester ini.',
                    'data' => [
                        'semester_aktif' => [
                            'id' => $semesterAktif->id,
                            'nama_semester' => $semesterAktif->nama_semester,
                            'tahun_akademik' => $semesterAktif->tahunAkademik->tahun_akademik ?? null
                        ],
                        'kurikulum' => [
                            'id' => $kurikulum->id,
                            'nama_kurikulum' => $kurikulum->nama_kurikulum
                        ],
                        'jumlah_matkul_tersedia' => 0,
                        'matkul_pilihan' => []
                    ]
                ], 200);
            }

            // Grouping berdasarkan mata kuliah
            $mataKuliahTersedia = $kelasMk->groupBy('id_mk')
                ->map(function ($kelasGroup) {
                    $mataKuliah = $kelasGroup->first()->mataKuliah;

                    return [
                        'id' => $mataKuliah->id,
                        'kode_mk' => $mataKuliah->kode_mk,
                        'nama_mk' => $mataKuliah->nama_mk,
                        'sks' => $mataKuliah->sks,
                        'semester_rekomendasi' => $mataKuliah->semester_rekomendasi,

                        // Daftar kelas yang tersedia untuk mata kuliah ini (hanya kelas yang sesuai dengan kelas pararel mahasiswa)
                        'kelas_tersedia' => $kelasGroup->map(function ($kelas) {
                            // Ambil dosen pertama dari relasi dosenKelasMk
                            $dosenInfo = null;
                            if ($kelas->dosenKelasMk && $kelas->dosenKelasMk->count() > 0) {
                                $firstDosen = $kelas->dosenKelasMk->first();
                                if ($firstDosen && $firstDosen->dosen) {
                                    $dosenInfo = [
                                        'id' => $firstDosen->dosen->id,
                                        'nama_dosen' => $firstDosen->dosen->nama_dosen,
                                        'nup' => $firstDosen->dosen->nup
                                    ];
                                }
                            }

                            return [
                                'id' => $kelas->id,
                                'kode_kelas_mk' => $kelas->kode_kelas_mk,
                                'kuota' => $kelas->kuota,
                                'tersisa' => $kelas->kuota - $this->getJumlahMahasiswaDiKelas($kelas->id),

                                // Informasi tambahan
                                'kelas_pararel' => $kelas->kelasPararel ? [
                                    'id' => $kelas->kelasPararel->id,
                                    'nama_kelas' => $kelas->kelasPararel->nama_kelas
                                ] : null,

                                'jenis_kelas' => $kelas->jenisKelas ? [
                                    'id' => $kelas->jenisKelas->id,
                                    'nama_kelas' => $kelas->jenisKelas->nama_kelas
                                ] : null,

                                'dosen' => $dosenInfo
                            ];
                        })->sortBy('kode_kelas_mk')->values(),

                        'jumlah_kelas_tersedia' => $kelasGroup->count(),
                        'total_kuota' => $kelasGroup->sum('kuota'),
                        'total_tersisa' => $kelasGroup->sum(function ($kelas) {
                            return $kelas->kuota - $this->getJumlahMahasiswaDiKelas($kelas->id);
                        })
                    ];
                })
                ->sortBy('kode_mk')
                ->values();

            // Filter hanya mata kuliah yang memiliki kelas tersedia
            $mataKuliahTersedia = $mataKuliahTersedia->filter(function ($mk) {
                return $mk['jumlah_kelas_tersedia'] > 0 && $mk['total_tersisa'] > 0;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Daftar mata kuliah pilihan berhasil diambil',
                'data' => [
                    'semester_aktif' => [
                        'id' => $semesterAktif->id,
                        'nama_semester' => $semesterAktif->nama_semester,
                        'tahun_akademik' => $semesterAktif->tahunAkademik->tahun_akademik ?? null
                    ],
                    'kurikulum' => [
                        'id' => $kurikulum->id,
                        'nama_kurikulum' => $kurikulum->nama_kurikulum
                    ],
                    'krs' => [
                        'id' => $krsAktif?->id,
                        'status' => $krsStatus,
                        'is_locked' => $isLocked
                    ],
                    'jumlah_matkul_tersedia' => $mataKuliahTersedia->count(),
                    'matkul_pilihan' => $mataKuliahTersedia
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar mata kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pengajuan KRS ke Dosen Wali
     */
    public function pengajuanKrs(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            // Validasi apakah mahasiswa memiliki kelas pararel
            if (!$mahasiswa->id_kelas_pararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum terdaftar di kelas pararel. Silakan hubungi admin untuk didaftarkan.'
                ], 403);
            }

            // Validasi apakah mahasiswa memiliki dosen wali
            if (!$mahasiswa->id_dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum memiliki dosen wali. Silakan hubungi admin untuk didaftarkan.'
                ], 403);
            }

            // Validasi input
            $request->validate([
                'kelas_mk_ids' => 'required|array|min:1',
                'kelas_mk_ids.*' => 'required|exists:kelas_mk,id',
            ]);

            $kelasMkIds = $request->input('kelas_mk_ids');

            // Cek semester aktif
            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            // Cek apakah sudah ada KRS sebelumnya untuk semester ini
            $existingKrs = Krs::where('id_mahasiswa', $mahasiswa->id)
                ->where('id_semester', $semesterAktif->id)
                ->whereIn('status', ['Draft', 'Menunggu Verifikasi', 'Disetujui', 'Selesai']) // Semua status kecuali Ditolak
                ->first();

            if ($existingKrs) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah memiliki KRS untuk semester ini',
                    'current_status' => $existingKrs->status
                ], 409);
            }

            // Validasi kelas MK - cek apakah kelas MK tersebut:
            // 1. Tersedia di semester aktif
            // 2. Sesuai dengan kelas pararel mahasiswa
            // 3. Masih ada kuota
            $kelasMkValid = KelasMk::whereIn('id', $kelasMkIds)
                ->where('id_semester', $semesterAktif->id)
                ->where('id_kelas_pararel', $mahasiswa->id_kelas_pararel) // Harus sesuai kelas pararel mahasiswa
                ->with(['mataKuliah:id,sks,nama_mk,kode_mk'])
                ->get();

            // Cek apakah semua kelas yang dipilih valid
            if ($kelasMkValid->count() !== count($kelasMkIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa kelas tidak valid, tidak tersedia di semester aktif, atau tidak sesuai dengan kelas pararel Anda'
                ], 400);
            }

            // Validasi kuota untuk setiap kelas
            $kelasOverload = [];
            foreach ($kelasMkValid as $kelasMk) {
                $jumlahMahasiswaDiKelas = $this->getJumlahMahasiswaDiKelas($kelasMk->id);
                if ($jumlahMahasiswaDiKelas >= $kelasMk->kuota) {
                    $kelasOverload[] = [
                        'kode_kelas' => $kelasMk->kode_kelas_mk,
                        'mata_kuliah' => $kelasMk->mataKuliah->nama_mk,
                        'kuota' => $kelasMk->kuota,
                        'terisi' => $jumlahMahasiswaDiKelas
                    ];
                }
            }

            if (!empty($kelasOverload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa kelas sudah penuh',
                    'kelas_penuh' => $kelasOverload
                ], 400);
            }

            // Hitung total SKS
            $totalSks = $kelasMkValid->sum('mataKuliah.sks');

            // Validasi jumlah SKS maksimal (misalnya 24 SKS per semester)
            $maxSks = $mahasiswa->jumlah_sks_max ?? 24; // Gunakan jumlah SKS maksimal dari mahasiswa atau default 24
            if ($totalSks > $maxSks) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah SKS melebihi batas maksimal {$maxSks} SKS",
                    'jumlah_sks_diambil' => $totalSks,
                    'batas_sks' => $maxSks
                ], 400);
            }

            DB::beginTransaction();

            // Buat KRS baru dengan status Menunggu Verifikasi
            $krs = Krs::create([
                'id_mahasiswa' => $mahasiswa->id,
                'id_semester' => $semesterAktif->id,
                'tanggal_pengisian' => now()->toDateString(),
                'status' => 'Menunggu Verifikasi', // Status awal adalah Menunggu Verifikasi
                'jumlah_sks_diambil' => $totalSks,
            ]);

            // Buat detail KRS
            foreach ($kelasMkValid as $kelasMk) {
                KrsDetail::create([
                    'id_krs' => $krs->id,
                    'id_kelas_mk' => $kelasMk->id,
                    'sks_diambil' => $kelasMk->mataKuliah->sks,
                ]);
            }

            DB::commit();

            // Load relasi untuk response
            $krs->load([
                'mahasiswa:id,nama_mahasiswa,nim,id_kelas_pararel,id_dosen',
                'mahasiswa.dosenWali:id,nama_dosen,nup',
                'semester:id,nama_semester',
                'dosenWali:id,nama_dosen,nup', // Relasi ke dosen wali dari KRS (akan kosong karena belum disetujui)
                'krsDetail:id,id_krs,id_kelas_mk,sks_diambil,created_at',
                'krsDetail.kelasMk:id,kode_kelas_mk,id_mk,kuota,id_semester,id_jenis_kelas,id_kelas_pararel',
                'krsDetail.kelasMk.mataKuliah:id,kode_mk,nama_mk,sks',
                'krsDetail.kelasMk.kelasPararel:id,nama_kelas',
                'krsDetail.kelasMk.jenisKelas:id,nama_kelas'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil diajukan ke dosen wali untuk verifikasi',
                'data' => [
                    'id' => $krs->id,
                    'id_mahasiswa' => $krs->id_mahasiswa,
                    'nim' => $krs->mahasiswa->nim ?? null,
                    'nama_mahasiswa' => $krs->mahasiswa->nama_mahasiswa ?? null,
                    'id_semester' => $krs->id_semester,
                    'nama_semester' => $krs->semester->nama_semester ?? null,
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'status' => $krs->status,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $krs->krsDetail->count(),
                    'dosen_wali' => [
                        'id' => $krs->mahasiswa->dosenWali->id ?? null,
                        'nama_dosen' => $krs->mahasiswa->dosenWali->nama_dosen ?? null,
                        'nup' => $krs->mahasiswa->dosenWali->nup ?? null
                    ],
                    'kelas_pararel_mahasiswa' => [
                        'id' => $krs->mahasiswa->id_kelas_pararel,
                        'nama_kelas' => $krs->mahasiswa->kelasPararel->nama_kelas ?? null
                    ],
                    'detail' => $krs->krsDetail->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'id_kelas_mk' => $detail->id_kelas_mk,
                            'kode_kelas_mk' => $detail->kelasMk->kode_kelas_mk ?? null,
                            'kelas_pararel' => [
                                'id' => $detail->kelasMk->id_kelas_pararel,
                                'nama_kelas' => $detail->kelasMk->kelasPararel->nama_kelas ?? null
                            ],
                            'mata_kuliah' => [
                                'kode_mk' => $detail->kelasMk->mataKuliah->kode_mk ?? null,
                                'nama_mk' => $detail->kelasMk->mataKuliah->nama_mk ?? null,
                                'sks' => $detail->kelasMk->mataKuliah->sks ?? 0,
                            ],
                            'jenis_kelas' => [
                                'id' => $detail->kelasMk->id_jenis_kelas,
                                'nama_kelas' => $detail->kelasMk->jenisKelas->nama_kelas ?? null
                            ],
                            'sks_diambil' => $detail->sks_diambil,
                            'kuota_kelas' => $detail->kelasMk->kuota ?? 0,
                            'jumlah_terisi' => $this->getJumlahMahasiswaDiKelas($detail->id_kelas_mk)
                        ];
                    }),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengajukan KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simpan KRS sebagai draft (opsional jika ingin menyimpan sementara)
     */
    public function simpanDraftKrs(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            // Validasi apakah mahasiswa memiliki kelas pararel
            if (!$mahasiswa->id_kelas_pararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum terdaftar di kelas pararel. Silakan hubungi admin untuk didaftarkan.'
                ], 403);
            }

            // Validasi apakah mahasiswa memiliki dosen wali
            if (!$mahasiswa->id_dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum memiliki dosen wali. Silakan hubungi admin untuk didaftarkan.'
                ], 403);
            }

            // Validasi input
            $request->validate([
                'kelas_mk_ids' => 'required|array|min:1',
                'kelas_mk_ids.*' => 'required|exists:kelas_mk,id',
            ]);

            $kelasMkIds = $request->input('kelas_mk_ids');

            // Cek semester aktif
            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            // Cek apakah sudah ada KRS sebelumnya untuk semester ini
            $existingKrs = Krs::where('id_mahasiswa', $mahasiswa->id)
                ->where('id_semester', $semesterAktif->id)
                ->whereIn('status', ['Draft', 'Menunggu Verifikasi', 'Disetujui', 'Selesai'])
                ->first();

            if ($existingKrs) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah memiliki KRS untuk semester ini',
                    'current_status' => $existingKrs->status
                ], 409);
            }

            // Validasi kelas MK - cek apakah kelas MK tersebut:
            // 1. Tersedia di semester aktif
            // 2. Sesuai dengan kelas pararel mahasiswa
            $kelasMkValid = KelasMk::whereIn('id', $kelasMkIds)
                ->where('id_semester', $semesterAktif->id)
                ->where('id_kelas_pararel', $mahasiswa->id_kelas_pararel)
                ->with(['mataKuliah:id,sks,nama_mk,kode_mk'])
                ->get();

            if ($kelasMkValid->count() !== count($kelasMkIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa kelas tidak valid, tidak tersedia di semester aktif, atau tidak sesuai dengan kelas pararel Anda'
                ], 400);
            }

            // Hitung total SKS
            $totalSks = $kelasMkValid->sum('mataKuliah.sks');

            // Validasi jumlah SKS maksimal
            $maxSks = $mahasiswa->jumlah_sks_max ?? 24;
            if ($totalSks > $maxSks) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah SKS melebihi batas maksimal {$maxSks} SKS",
                    'jumlah_sks_diambil' => $totalSks,
                    'batas_sks' => $maxSks
                ], 400);
            }

            DB::beginTransaction();

            // Buat KRS baru dengan status Draft
            $krs = Krs::create([
                'id_mahasiswa' => $mahasiswa->id,
                'id_semester' => $semesterAktif->id,
                'tanggal_pengisian' => now()->toDateString(),
                'status' => 'Draft', // Status draft
                'jumlah_sks_diambil' => $totalSks,
            ]);

            // Buat detail KRS
            foreach ($kelasMkValid as $kelasMk) {
                KrsDetail::create([
                    'id_krs' => $krs->id,
                    'id_kelas_mk' => $kelasMk->id,
                    'sks_diambil' => $kelasMk->mataKuliah->sks,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil disimpan sebagai draft',
                'data' => [
                    'id' => $krs->id,
                    'status' => $krs->status,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $kelasMkValid->count(),
                    'dosen_wali' => [
                        'id' => $mahasiswa->id_dosen,
                        'nama_dosen' => $mahasiswa->dosenWali->nama_dosen ?? null,
                        'nup' => $mahasiswa->dosenWali->nup ?? null
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan draft KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status KRS dari Draft ke Menunggu Verifikasi
     */
    public function submitKrs(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::Guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            // Validasi apakah mahasiswa memiliki dosen wali
            if (!$mahasiswa->id_dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum memiliki dosen wali. Silakan hubungi admin untuk didaftarkan.'
                ], 403);
            }

            $krs = Krs::where('id', $id)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->where('status', 'Draft') // Hanya bisa submit jika statusnya Draft
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan atau tidak dapat disubmit'
                ], 404);
            }

            // Update status KRS menjadi Menunggu Verifikasi
            $krs->update([
                'status' => 'Menunggu Verifikasi'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil diajukan ke dosen wali untuk verifikasi',
                'data' => [
                    'id' => $krs->id,
                    'status' => $krs->status,
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat submit KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batalkan pengajuan KRS (sebelum disetujui)
     */
    public function batalPengajuanKrs(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            $krs = Krs::where('id', $id)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->whereIn('status', ['Draft', 'Menunggu Verifikasi']) // Bisa dibatalkan jika Draft atau Menunggu Verifikasi
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan atau tidak dapat dibatalkan'
                ], 404);
            }

            DB::beginTransaction();

            // Hapus detail KRS terlebih dahulu
            $krs->krsDetail()->delete();

            // Hapus KRS
            $krs->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan KRS berhasil dibatalkan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membatalkan KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lihat status pengajuan KRS terbaru
     */
    public function statusPengajuanKrs(): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('mahasiswa') || !$user->mahasiswa) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $mahasiswa = $user->mahasiswa;

            // Ambil KRS terbaru untuk semester aktif
            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            $krs = Krs::where('id_mahasiswa', $mahasiswa->id)
                ->where('id_semester', $semesterAktif->id)
                ->latest('created_at')
                ->with([
                    'semester:id,nama_semester',
                    'krsDetail:id,id_krs,id_kelas_mk,sks_diambil',
                    'krsDetail.kelasMk:id,kode_kelas_mk,id_mk,id_kelas_pararel',
                    'krsDetail.kelasMk.mataKuliah:id,kode_mk,nama_mk,sks',
                    'krsDetail.kelasMk.kelasPararel:id,nama_kelas',
                    'mahasiswa.dosenWali:id,nama_dosen,nup', // Ambil dosen wali dari mahasiswa
                    'dosenWali:id,nama_dosen,nup' // Ambil dosen wali dari KRS (akan terisi jika sudah disetujui)
                ])
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada pengajuan KRS untuk semester ini',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status pengajuan KRS berhasil diambil',
                'data' => [
                    'id' => $krs->id,
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'tanggal_verifikasi' => $krs->tanggal_verifikasi,
                    'status' => $krs->status,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $krs->krsDetail->count(),
                    'dosen_wali' => [
                        'id' => $krs->dosenWali->id ?? $krs->mahasiswa->id_dosen, // Ambil dari KRS jika sudah disetujui, jika tidak ambil dari mahasiswa
                        'nama_dosen' => $krs->dosenWali->nama_dosen ?? $krs->mahasiswa->dosenWali->nama_dosen,
                        'nup' => $krs->dosenWali->nup ?? $krs->mahasiswa->dosenWali->nup
                    ],
                    'kelas_pararel_mahasiswa' => [
                        'id' => $krs->mahasiswa->id_kelas_pararel,
                        'nama_kelas' => $krs->mahasiswa->kelasPararel->nama_kelas ?? null
                    ],
                    'detail' => $krs->krsDetail->map(function ($detail) {
                        return [
                            'id_kelas_mk' => $detail->id_kelas_mk,
                            'kode_kelas_mk' => $detail->kelasMk->kode_kelas_mk ?? null,
                            'kelas_pararel' => [
                                'id' => $detail->kelasMk->id_kelas_pararel,
                                'nama_kelas' => $detail->kelasMk->kelasPararel->nama_kelas ?? null
                            ],
                            'mata_kuliah' => [
                                'kode_mk' => $detail->kelasMk->mataKuliah->kode_mk ?? null,
                                'nama_mk' => $detail->kelasMk->mataKuliah->nama_mk ?? null,
                                'sks' => $detail->kelasMk->mataKuliah->sks ?? 0,
                            ],
                            'sks_diambil' => $detail->sks_diambil,
                        ];
                    }),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function untuk menghitung jumlah mahasiswa di kelas
     */
    private function getJumlahMahasiswaDiKelas(string $idKelasMk): int
    {
        return KrsDetail::where('id_kelas_mk', $idKelasMk)
            ->join('krs', 'krs_detail.id_krs', '=', 'krs.id')
            ->whereIn('krs.status', ['Disetujui', 'Selesai']) // Termasuk yang sudah disetujui
            ->count();
    }
}
