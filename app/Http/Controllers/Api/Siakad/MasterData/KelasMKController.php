<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Semester;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\JenisKelas;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\KelasPararel;
use App\Models\MasterData\TahunAkademik;
use Illuminate\Validation\ValidationException;

class KelasMKController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan
            $kelasMks = KelasMk::with(['mataKuliah', 'kelasPararel', 'semester', 'jenisKelas'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Kelas MK',
                'data' => $kelasMks
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas MK.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        try {
            // Ambil data mata kuliah dengan relasi kurikulum dan prodi
            $mataKuliahs = MataKuliah::select('id', 'id_kurikulum', 'kode_mk', 'nama_mk')
                ->with(['kurikulum:id,id_prodi,nama_kurikulum'])
                ->get();

            // Ambil data kelas pararel
            $kelasPararels = KelasPararel::select('id', 'id_prodi', 'nama_kelas')
                ->get();

            // Ambil data prodi
            $prodis = Prodi::select('id', 'nama_prodi')->get();

            // Ambil data jenis kelas
            $jenisKelas = JenisKelas::select('id', 'nama_kelas')->get();

            // Ambil data kurikulum untuk struktur lengkap
            $kurikulums = Kurikulum::select('id', 'id_prodi', 'nama_kurikulum')->get();

            // Ambil tahun akademik aktif beserta satu semester aktif saja
            $tahunAkademikAktif = TahunAkademik::select('id', 'tahun_akademik', 'status_aktif')
                ->with(['semester' => function ($query) {
                    $query->select('id', 'nama_semester', 'id_tahun_akademik')
                        ->where('status', 'aktif')
                        ->limit(1); // Ambil hanya satu semester
                }])
                ->where('status_aktif', true)
                ->first();

            $semesterAktif = null;
            if ($tahunAkademikAktif && $tahunAkademikAktif->semester->count() > 0) {
                $semesterAktif = $tahunAkademikAktif->semester->first(); // Ambil satu semester
            }

            // Struktur nested: prodi -> kurikulum -> mata kuliah
            $dropdownStructure = [
                'prodi' => $prodis->map(function ($prodi) use ($kurikulums, $mataKuliahs, $kelasPararels) {
                    // Dapatkan kurikulum yang terkait dengan prodi ini
                    $kurikulumProdi = $kurikulums->where('id_prodi', $prodi->id);

                    return [
                        'id' => $prodi->id,
                        'nama_prodi' => $prodi->nama_prodi,
                        'kurikulum' => $kurikulumProdi->map(function ($kurikulum) use ($mataKuliahs) {
                            return [
                                'id' => $kurikulum->id,
                                'nama_kurikulum' => $kurikulum->nama_kurikulum,
                                'mata_kuliah' => $mataKuliahs
                                    ->where('id_kurikulum', $kurikulum->id)
                                    ->map(function ($mk) {
                                        return [
                                            'id' => $mk->id,
                                            'kode_mk' => $mk->kode_mk,
                                            'nama_mk' => $mk->nama_mk,
                                            'id_kurikulum' => $mk->id_kurikulum
                                        ];
                                    })
                            ];
                        }),
                        'kelas_pararel' => $kelasPararels
                            ->where('id_prodi', $prodi->id)
                            ->map(function ($kp) {
                                return [
                                    'id' => $kp->id,
                                    'nama_kelas' => $kp->nama_kelas
                                ];
                            })
                    ];
                }),
                'jenis_kelas' => $jenisKelas,
                'tahun_akademik' => $tahunAkademikAktif ? [
                    'id' => $tahunAkademikAktif->id,
                    'tahun_akademik' => $tahunAkademikAktif->tahun_akademik,
                    'status_aktif' => $tahunAkademikAktif->status_aktif,
                ] : null,
                'semester' => $semesterAktif
            ];

            return response()->json([
                'success' => true,
                'data' => $dropdownStructure
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mk' => 'required|exists:mata_kuliah,id',
                'id_kelas_pararel' => 'required|exists:kelas_pararel,id',
                'id_semester' => 'required|exists:semester,id',
                'id_jenis_kelas' => 'required|exists:jenis_kelas,id',
                'kode_kelas_mk' => 'required|string|max:255|unique:kelas_mk,kode_kelas_mk',
                'kuota' => 'required|integer|min:0',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $kelasMk = KelasMk::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil dibuat.',
                'data' => $kelasMk
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource for editing.
     */
    public function edit(string $id): JsonResponse
    {
        try {
            // Ambil data kelas MK berdasarkan ID
            $kelasMk = KelasMk::with([
                'mataKuliah.kurikulum',
                'kelasPararel',
                'semester.tahunAkademik',
                'jenisKelas'
            ])->find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            // Ambil data mata kuliah dengan relasi kurikulum dan prodi
            $mataKuliahs = MataKuliah::select('id', 'id_kurikulum', 'kode_mk', 'nama_mk')
                ->with(['kurikulum:id,id_prodi,nama_kurikulum'])
                ->get();

            // Ambil data kelas pararel
            $kelasPararels = KelasPararel::select('id', 'id_prodi', 'nama_kelas')
                ->get();

            // Ambil data prodi
            $prodis = Prodi::select('id', 'nama_prodi')->get();

            // Ambil data jenis kelas
            $jenisKelas = JenisKelas::select('id', 'nama_kelas')->get();

            // Ambil data kurikulum untuk struktur lengkap
            $kurikulums = Kurikulum::select('id', 'id_prodi', 'nama_kurikulum')->get();

            // Ambil tahun akademik aktif beserta satu semester aktif saja
            $tahunAkademikAktif = TahunAkademik::select('id', 'tahun_akademik', 'status_aktif')
                ->with(['semester' => function ($query) {
                    $query->select('id', 'nama_semester', 'id_tahun_akademik')
                        ->where('status', 'aktif')
                        ->limit(1); // Ambil hanya satu semester
                }])
                ->where('status_aktif', true)
                ->first();

            $semesterAktif = null;
            if ($tahunAkademikAktif && $tahunAkademikAktif->semester->count() > 0) {
                $semesterAktif = $tahunAkademikAktif->semester->first(); // Ambil satu semester
            }

            // Struktur nested: prodi -> kurikulum -> mata kuliah
            $dropdownStructure = [
                'prodi' => $prodis->map(function ($prodi) use ($kurikulums, $mataKuliahs, $kelasPararels) {
                    // Dapatkan kurikulum yang terkait dengan prodi ini
                    $kurikulumProdi = $kurikulums->where('id_prodi', $prodi->id);

                    return [
                        'id' => $prodi->id,
                        'nama_prodi' => $prodi->nama_prodi,
                        'kurikulum' => $kurikulumProdi->map(function ($kurikulum) use ($mataKuliahs) {
                            return [
                                'id' => $kurikulum->id,
                                'nama_kurikulum' => $kurikulum->nama_kurikulum,
                                'mata_kuliah' => $mataKuliahs
                                    ->where('id_kurikulum', $kurikulum->id)
                                    ->map(function ($mk) {
                                        return [
                                            'id' => $mk->id,
                                            'kode_mk' => $mk->kode_mk,
                                            'nama_mk' => $mk->nama_mk,
                                            'id_kurikulum' => $mk->id_kurikulum
                                        ];
                                    })
                            ];
                        }),
                        'kelas_pararel' => $kelasPararels
                            ->where('id_prodi', $prodi->id)
                            ->map(function ($kp) {
                                return [
                                    'id' => $kp->id,
                                    'nama_kelas' => $kp->nama_kelas
                                ];
                            })
                    ];
                }),
                'jenis_kelas' => $jenisKelas,
                'tahun_akademik' => $tahunAkademikAktif ? [
                    'id' => $tahunAkademikAktif->id,
                    'tahun_akademik' => $tahunAkademikAktif->tahun_akademik,
                    'status_aktif' => $tahunAkademikAktif->status_aktif,
                ] : null,
                'semester' => $semesterAktif
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'kelas_mk' => $kelasMk,
                    'dropdown_structure' => $dropdownStructure
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $kelasMk = KelasMk::find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mk' => 'sometimes|required|exists:mata_kuliah,id',
                'id_kelas_pararel' => 'sometimes|required|exists:kelas_pararel,id',
                'id_semester' => 'sometimes|required|exists:semester,id',
                'id_jenis_kelas' => 'sometimes|required|exists:jenis_kelas,id',
                'kode_kelas_mk' => 'sometimes|required|string|max:255|unique:kelas_mk,kode_kelas_mk,' . $id,
                'kuota' => 'sometimes|required|integer|min:0',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            // Update data kelas MK
            $kelasMk->fill($request->all());
            $kelasMk->save();

            // Refresh data dengan relasi
            $kelasMk->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil diperbarui.',
                'data' => $kelasMk
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $kelasMk = KelasMk::findOrFail($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            $kelasMk->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
