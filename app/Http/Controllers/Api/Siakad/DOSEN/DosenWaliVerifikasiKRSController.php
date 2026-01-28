<?php

namespace App\Http\Controllers\Api\Siakad\DOSEN;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Krs;
use App\Models\MasterData\KrsDetail;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use App\Models\MasterData\Dosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DosenWaliVerifikasiKRSController extends Controller
{
    /**
     * Menampilkan daftar KRS yang perlu diverifikasi oleh dosen wali
     */
    public function daftarKrsPerluVerifikasi(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $dosen = $user->dosen;

            // Cek apakah dosen ini benar-benar merupakan dosen wali
            $mahasiswaBimbingan = Mahasiswa::where('id_dosen', $dosen->id)
                ->pluck('id')
                ->toArray();

            if (empty($mahasiswaBimbingan)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Anda tidak memiliki mahasiswa bimbingan',
                    'data' => [
                        'krs_list' => [],
                        'total_krs' => 0
                    ]
                ], 200);
            }

            // Cek semester aktif
            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            // Ambil KRS dari mahasiswa bimbingan yang statusnya Menunggu Verifikasi
            $krsList = Krs::whereIn('id_mahasiswa', $mahasiswaBimbingan)
                ->where('id_semester', $semesterAktif->id)
                ->where('status', 'Menunggu Verifikasi')
                ->with([
                    'mahasiswa:id,nama_mahasiswa,nim,id_kelas_pararel',
                    'mahasiswa.kelasPararel:id,nama_kelas',
                    'semester:id,id_tahun_akademik,nama_semester',
                    'semester.tahunAkademik:id,tahun_akademik',
                    'krsDetail:id,id_krs,id_kelas_mk,sks_diambil',
                    'krsDetail.kelasMk:id,kode_kelas_mk,id_mk,kuota,id_semester,id_kelas_pararel',
                    'krsDetail.kelasMk.mataKuliah:id,kode_mk,nama_mk,sks',
                    'krsDetail.kelasMk.kelasPararel:id,nama_kelas'
                ])
                ->get();

            $formattedKrs = $krsList->map(function ($krs) {
                return [
                    'id' => $krs->id,
                    'mahasiswa' => [
                        'id' => $krs->mahasiswa->id,
                        'nim' => $krs->mahasiswa->nim,
                        'nama_mahasiswa' => $krs->mahasiswa->nama_mahasiswa,
                        'kelas_pararel' => [
                            'id' => $krs->mahasiswa->id_kelas_pararel,
                            'nama_kelas' => $krs->mahasiswa->kelasPararel->nama_kelas ?? null
                        ]
                    ],
                    'semester' => [
                        'id' => $krs->semester->id,
                        'nama_semester' => $krs->semester->nama_semester,
                        'tahun_akademik' => $krs->semester->tahunAkademik?->tahun_akademik,

                    ],
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $krs->krsDetail->count(),
                    'detail' => $krs->krsDetail->map(function ($detail) {
                        return [
                            'id_kelas_mk' => $detail->id_kelas_mk,
                            'kode_kelas_mk' => $detail->kelasMk->kode_kelas_mk ?? null,
                            'mata_kuliah' => [
                                'kode_mk' => $detail->kelasMk->mataKuliah->kode_mk ?? null,
                                'nama_mk' => $detail->kelasMk->mataKuliah->nama_mk ?? null,
                                'sks' => $detail->kelasMk->mataKuliah->sks ?? 0,
                            ],
                            'kelas_pararel' => [
                                'id' => $detail->kelasMk->id_kelas_pararel,
                                'nama_kelas' => $detail->kelasMk->kelasPararel->nama_kelas ?? null
                            ],
                            'sks_diambil' => $detail->sks_diambil,
                        ];
                    }),
                    'status' => $krs->status
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar KRS yang perlu diverifikasi berhasil diambil',
                'data' => [
                    'krs_list' => $formattedKrs,
                    'total_krs' => $formattedKrs->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan detail KRS tertentu
     */
    public function detailKrs(string $id): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $dosen = $user->dosen;

            // Cek apakah KRS milik mahasiswa bimbingan dosen ini
            $krs = Krs::where('id', $id)
                ->whereHas('mahasiswa', function ($query) use ($dosen) {
                    $query->where('id_dosen', $dosen->id);
                })
                ->with([
                    'mahasiswa:id,id_dosen,nama_mahasiswa,nim,id_kelas_pararel,no_hp',
                    'mahasiswa.kelasPararel:id,nama_kelas',
                    'mahasiswa.dosenWali:id,nama_dosen,nup',
                    'semester:id,nama_semester',
                    'krsDetail:id,id_krs,id_kelas_mk,sks_diambil',
                    'krsDetail.kelasMk:id,kode_kelas_mk,id_mk,kuota,id_semester,id_kelas_pararel,id_jenis_kelas',
                    'krsDetail.kelasMk.mataKuliah:id,kode_mk,nama_mk,sks,semester_rekomendasi',
                    'krsDetail.kelasMk.kelasPararel:id,nama_kelas',
                    'krsDetail.kelasMk.jenisKelas:id,nama_kelas',
                    'krsDetail.kelasMk.dosenKelasMk.dosen:id,nama_dosen,nup'
                ])
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan atau bukan milik mahasiswa bimbingan Anda'
                ], 404);
            }

            // Ambil informasi jumlah mahasiswa di setiap kelas
            $kelasWithCount = $krs->krsDetail->map(function ($detail) {
                $jumlahDiKelas = $this->getJumlahMahasiswaDiKelas($detail->id_kelas_mk);
                return [
                    'id_kelas_mk' => $detail->id_kelas_mk,
                    'kode_kelas_mk' => $detail->kelasMk->kode_kelas_mk ?? null,
                    'mata_kuliah' => [
                        'id' => $detail->kelasMk->mataKuliah->id ?? null,
                        'kode_mk' => $detail->kelasMk->mataKuliah->kode_mk ?? null,
                        'nama_mk' => $detail->kelasMk->mataKuliah->nama_mk ?? null,
                        'sks' => $detail->kelasMk->mataKuliah->sks ?? 0,
                        'semester_rekomendasi' => $detail->kelasMk->mataKuliah->semester_rekomendasi ?? null,
                    ],
                    'kelas_info' => [
                        'kuota' => $detail->kelasMk->kuota ?? 0,
                        'jumlah_terisi' => $jumlahDiKelas,
                        'tersisa' => ($detail->kelasMk->kuota ?? 0) - $jumlahDiKelas,
                        'kelas_pararel' => [
                            'id' => $detail->kelasMk->id_kelas_pararel,
                            'nama_kelas' => $detail->kelasMk->kelasPararel->nama_kelas ?? null
                        ],
                        'jenis_kelas' => [
                            'id' => $detail->kelasMk->id_jenis_kelas,
                            'nama_kelas' => $detail->kelasMk->jenisKelas->nama_kelas ?? null
                        ],
                        'dosen_pengampu' => collect($detail->kelasMk->dosenKelasMk)->first() ? [
                            'id' => collect($detail->kelasMk->dosenKelasMk)->first()->dosen->id ?? null,
                            'nama_dosen' => collect($detail->kelasMk->dosenKelasMk)->first()->dosen->nama_dosen ?? null,
                            'nup' => collect($detail->kelasMk->dosenKelasMk)->first()->dosen->nup ?? null
                        ] : null
                    ],
                    'sks_diambil' => $detail->sks_diambil,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Detail KRS berhasil diambil',
                'data' => [
                    'id' => $krs->id,
                    'mahasiswa' => [
                        'id' => $krs->mahasiswa->id,
                        'nim' => $krs->mahasiswa->nim,
                        'nama_mahasiswa' => $krs->mahasiswa->nama_mahasiswa,
                        'kelas_pararel' => [
                            'id' => $krs->mahasiswa->id_kelas_pararel,
                            'nama_kelas' => $krs->mahasiswa->kelasPararel->nama_kelas ?? null
                        ],
                        // 'jumlah_sks_max' => $krs->mahasiswa->jumlah_sks_max ?? 24,
                        'no_hp' => $krs->mahasiswa->no_hp,
                    ],
                    'dosen_wali' => [
                        'id' => $krs->mahasiswa->dosenWali->id ?? null,
                        'nama_dosen' => $krs->mahasiswa->dosenWali->nama_dosen ?? null,
                        'nup' => $krs->mahasiswa->dosenWali->nup ?? null
                    ],
                    'semester' => [
                        'id' => $krs->semester->id,
                        'nama_semester' => $krs->semester->nama_semester
                    ],
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'status' => $krs->status,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $kelasWithCount->count(),
                    'detail' => $kelasWithCount
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyetujui KRS
     */
    public function approveKrs(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $dosen = $user->dosen;

            // Validasi input
            $request->validate([
                'catatan_verifikasi' => 'nullable|string|max:500',
            ]);

            // Cek apakah KRS milik mahasiswa bimbingan dosen ini
            $krs = Krs::where('id', $id)
                ->where('status', 'Menunggu Verifikasi')
                ->whereHas('mahasiswa', function ($query) use ($dosen) {
                    $query->where('id_dosen', $dosen->id);
                })
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan, sudah disetujui, atau bukan milik mahasiswa bimbingan Anda'
                ], 404);
            }

            // Ambil dosen wali dari mahasiswa
            $mahasiswa = $krs->mahasiswa;

            if (!$mahasiswa->id_dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak memiliki dosen wali'
                ], 400);
            }

            DB::beginTransaction();

            // Update status KRS dan isi kolom id_dosen_wali dengan dosen wali dari mahasiswa
            $krs->update([
                'status' => 'Disetujui',
                'tanggal_verifikasi' => now()->toDateString(),
                'id_dosen_wali' => $mahasiswa->id_dosen, // Ambil dari dosen wali mahasiswa
                'catatan_verifikasi' => $request->input('catatan_verifikasi', '')
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil disetujui',
                'data' => [
                    'id' => $krs->id,
                    'status' => $krs->status,
                    'tanggal_verifikasi' => $krs->tanggal_verifikasi,
                    'dosen_wali' => [
                        'id' => $krs->dosenWali->id,
                        'nama_dosen' => $krs->dosenWali->nama_dosen,
                        'nip' => $krs->dosenWali->nip
                    ],
                    'catatan_verifikasi' => $krs->catatan_verifikasi
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menolak KRS
     */
    public function rejectKrs(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $dosen = $user->dosen;

            // Validasi input
            $request->validate([
                'catatan_verifikasi' => 'required|string|max:500',
            ]);

            // Cek apakah KRS milik mahasiswa bimbingan dosen ini
            $krs = Krs::where('id', $id)
                ->where('status', 'Menunggu Verifikasi')
                ->whereHas('mahasiswa', function ($query) use ($dosen) {
                    $query->where('id_dosen', $dosen->id);
                })
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan, sudah disetujui, atau bukan milik mahasiswa bimbingan Anda'
                ], 404);
            }

            // Ambil dosen wali dari mahasiswa
            $mahasiswa = $krs->mahasiswa;

            if (!$mahasiswa->id_dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak memiliki dosen wali'
                ], 400);
            }

            DB::beginTransaction();

            // Update status KRS menjadi Ditolak
            $krs->update([
                'status' => 'Ditolak',
                'tanggal_verifikasi' => now()->toDateString(),
                'id_dosen_wali' => $mahasiswa->id_dosen, // Ambil dari dosen wali mahasiswa
                'catatan_verifikasi' => $request->input('catatan_verifikasi')
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil ditolak',
                'data' => [
                    'id' => $krs->id,
                    'status' => $krs->status,
                    'tanggal_verifikasi' => $krs->tanggal_verifikasi,
                    'dosen_wali' => [
                        'id' => $krs->dosenWali->id,
                        'nama_dosen' => $krs->dosenWali->nama_dosen,
                        'nip' => $krs->dosenWali->nip
                    ],
                    'catatan_verifikasi' => $krs->catatan_verifikasi
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak KRS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar KRS yang sudah diverifikasi (disetujui/ditolak)
     */
    public function daftarKrsTerverifikasi(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }

            $dosen = $user->dosen;

            // Cek apakah dosen ini benar-benar merupakan dosen wali
            $mahasiswaBimbingan = Mahasiswa::where('id_dosen', $dosen->id)
                ->pluck('id')
                ->toArray();

            if (empty($mahasiswaBimbingan)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Anda tidak memiliki mahasiswa bimbingan',
                    'data' => [
                        'krs_list' => [],
                        'total_krs' => 0
                    ]
                ], 200);
            }

            // Cek semester aktif
            $semesterAktif = Semester::where('status', 'Aktif')->first();
            if (!$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Tidak ada semester aktif'], 404);
            }

            // Ambil KRS dari mahasiswa bimbingan yang statusnya sudah diverifikasi
            $krsList = Krs::whereIn('id_mahasiswa', $mahasiswaBimbingan)
                ->where('id_semester', $semesterAktif->id)
                ->whereIn('status', ['Disetujui', 'Ditolak'])
                ->with([
                    'mahasiswa:id,nama_mahasiswa,nim,id_kelas_pararel',
                    'mahasiswa.kelasPararel:id,nama_kelas',
                    'semester:id,nama_semester',
                    'dosenWali:id,nama_dosen,nup',
                    'krsDetail:id,id_krs,id_kelas_mk,sks_diambil',
                    'krsDetail.kelasMk:id,kode_kelas_mk,id_mk',
                    'krsDetail.kelasMk.mataKuliah:id,kode_mk,nama_mk,sks'
                ])
                ->orderBy('tanggal_verifikasi', 'desc')
                ->get();

            $formattedKrs = $krsList->map(function ($krs) {
                return [
                    'id' => $krs->id,
                    'mahasiswa' => [
                        'id' => $krs->mahasiswa->id,
                        'nim' => $krs->mahasiswa->nim,
                        'nama_mahasiswa' => $krs->mahasiswa->nama_mahasiswa,
                        'kelas_pararel' => [
                            'id' => $krs->mahasiswa->id_kelas_pararel,
                            'nama_kelas' => $krs->mahasiswa->kelasPararel->nama_kelas ?? null
                        ]
                    ],
                    'dosen_verifikasi' => [
                        'id' => $krs->dosenWali->id,
                        'nama_dosen' => $krs->dosenWali->nama_dosen,
                        'nup' => $krs->dosenWali->nup
                    ],
                    'semester' => [
                        'id' => $krs->semester->id,
                        'nama_semester' => $krs->semester->nama_semester
                    ],
                    'tanggal_pengisian' => $krs->tanggal_pengisian,
                    'tanggal_verifikasi' => $krs->tanggal_verifikasi,
                    'status' => $krs->status,
                    'catatan_verifikasi' => $krs->catatan_verifikasi,
                    'jumlah_sks_diambil' => $krs->jumlah_sks_diambil,
                    'jumlah_matkul_diambil' => $krs->krsDetail->count(),
                    'detail' => $krs->krsDetail->map(function ($detail) {
                        return [
                            'id_kelas_mk' => $detail->id_kelas_mk,
                            'mata_kuliah' => [
                                'kode_mk' => $detail->kelasMk->mataKuliah->kode_mk ?? null,
                                'nama_mk' => $detail->kelasMk->mataKuliah->nama_mk ?? null,
                                'sks' => $detail->kelasMk->mataKuliah->sks ?? 0,
                            ],
                            'sks_diambil' => $detail->sks_diambil,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar KRS yang sudah diverifikasi berhasil diambil',
                'data' => [
                    'krs_list' => $formattedKrs,
                    'total_krs' => $formattedKrs->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar KRS terverifikasi',
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
