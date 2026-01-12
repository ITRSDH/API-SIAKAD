<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\TahunAkademik;
use Illuminate\Validation\ValidationException;

class MataKuliahController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            // Ambil tahun akademik aktif
            $tahunAkademikAktif = TahunAkademik::where('status_aktif', true)->first();

            // Ambil semua mata kuliah beserta relasi kurikulum dan prodi
            $mataKuliahs = MataKuliah::with(['kurikulum.prodi'])->get();

            // Kelompokkan berdasarkan prodi, lalu kurikulum, lalu semester
            $grouped = $mataKuliahs->groupBy([
                function ($mk) {
                    return $mk->kurikulum->prodi->id ?? null;
                }, // Grup berdasarkan ID Prodi
                function ($mk) {
                    return $mk->kurikulum->id ?? null;
                }, // Grup berdasarkan ID Kurikulum
                'semester_rekomendasi' // Grup berdasarkan semester
            ]);

            $result = [];

            foreach ($grouped as $idProdi => $kurikulums) {
                foreach ($kurikulums as $idKurikulum => $semesters) {
                    $prodi = null;
                    $kurikulum = null;

                    $semesterData = [];

                    foreach ($semesters as $semester => $mataKuliah) {
                        $semesterData[] = [
                            'semester' => $semester,
                            'mata_kuliah' => $mataKuliah->map(function ($mk) {
                                return [
                                    'id' => $mk->id,
                                    'kode_mk' => $mk->kode_mk,
                                    'nama_mk' => $mk->nama_mk,
                                    'sks' => $mk->sks,
                                    'teori' => $mk->teori,
                                    'praktikum' => $mk->praktikum,
                                    'klinik' => $mk->klinik,
                                ];
                            })->toArray()
                        ];

                        // Ambil data prodi dan kurikulum dari item mata kuliah pertama dalam semester
                        if (!$prodi) {
                            $prodi = [
                                'id' => $mataKuliah->first()->kurikulum->prodi->id,
                                'nama_prodi' => $mataKuliah->first()->kurikulum->prodi->nama_prodi
                            ];
                        }

                        if (!$kurikulum) {
                            $kurikulum = [
                                'id' => $mataKuliah->first()->kurikulum->id,
                                'nama_kurikulum' => $mataKuliah->first()->kurikulum->nama_kurikulum,
                            ];
                        }
                    }

                    $kurikulum['semesters'] = $semesterData;

                    $result[] = [
                        'tahun_akademik_aktif' => $tahunAkademikAktif ? [
                            'id' => $tahunAkademikAktif->id,
                            'tahun_akademik' => $tahunAkademikAktif->tahun_akademik,
                            'status_aktif' => $tahunAkademikAktif->status_aktif,
                        ] : null,
                        'prodi' => $prodi,
                        'kurikulum' => $kurikulum,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Data master berhasil diambil',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data master.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        try {
            $prodi = Prodi::all(['id', 'nama_prodi']);
            $kurikulum = Kurikulum::with('prodi')->get(['id', 'id_prodi', 'nama_kurikulum']);

            return response()->json([
                'success' => true,
                'message' => 'Data master untuk membuat mata kuliah',
                'data' => [
                    'prodi' => $prodi,
                    'kurikulum' => $kurikulum
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data master.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'id_kurikulum' => 'required|exists:kurikulum,id',
                'semester_rekomendasi' => 'required|integer|min:1|max:14',
                'mata_kuliah' => 'required|array|min:1',
                'mata_kuliah.*.kode_mk' => 'required|string|max:20|unique:mata_kuliah,kode_mk',
                'mata_kuliah.*.nama_mk' => 'required|string|max:255',
                'mata_kuliah.*.teori' => 'required|integer|min:0',
                'mata_kuliah.*.praktikum' => 'required|integer|min:0',
                'mata_kuliah.*.klinik' => 'required|integer|min:0',
            ]);

            // Opsional: Validasi bahwa kurikulum terkait dengan prodi
            $kurikulum = Kurikulum::where('id', $request->id_kurikulum)
                ->where('id_prodi', $request->id_prodi)
                ->first();

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak sesuai dengan prodi yang dipilih.'
                ], 422);
            }

            $createdMataKuliah = [];
            $totalSKSSemester = 0;

            foreach ($request->mata_kuliah as $mk) {
                // Hitung SKS per mata kuliah
                $sks = (int)$mk['teori'] + (int)$mk['praktikum'] + (int)$mk['klinik'];

                // Validasi: Total SKS per mata kuliah minimal 1
                if ($sks < 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total SKS per mata kuliah (teori + praktikum + klinik) harus minimal 1.'
                    ], 422);
                }

                // Tambahkan semester_rekomendasi dan id_kurikulum
                $mataKuliahData = [
                    'kode_mk' => $mk['kode_mk'],
                    'nama_mk' => $mk['nama_mk'],
                    'teori' => $mk['teori'],
                    'praktikum' => $mk['praktikum'],
                    'klinik' => $mk['klinik'],
                    'sks' => $sks,
                    'semester_rekomendasi' => $request->semester_rekomendasi,
                    'id_kurikulum' => $request->id_kurikulum,
                ];

                $mataKuliah = MataKuliah::create($mataKuliahData);
                $createdMataKuliah[] = $mataKuliah;
                $totalSKSSemester += $sks;
            }

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah berhasil dibuat.',
                'data' => $createdMataKuliah,
                'total_sks_semester' => $totalSKSSemester
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
                'message' => 'Terjadi kesalahan saat membuat mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::with(['kurikulum.prodi'])->find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data mata kuliah berhasil diambil.',
                'data' => [
                    'mata_kuliah' => [
                        'id' => $mataKuliah->id,
                        'id_kurikulum' => $mataKuliah->id_kurikulum,
                        'kode_mk' => $mataKuliah->kode_mk,
                        'nama_mk' => $mataKuliah->nama_mk,
                        'sks' => $mataKuliah->sks,
                        'teori' => $mataKuliah->teori,
                        'praktikum' => $mataKuliah->praktikum,
                        'klinik' => $mataKuliah->klinik,
                        'semester_rekomendasi' => $mataKuliah->semester_rekomendasi,
                        'prodi_nama' => $mataKuliah->kurikulum->prodi->nama_prodi ?? null,
                        'kurikulum_nama' => $mataKuliah->kurikulum->nama_kurikulum ?? null
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $semester): JsonResponse
    {
        try {
            $idKurikulum = $request->query('id_kurikulum');

            if (!$idKurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID Kurikulum wajib disertakan.'
                ], 422);
            }

            // Ambil data kurikulum untuk mendapatkan id_prodi
            $kurikulum = Kurikulum::find($idKurikulum);
            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak ditemukan.'
                ], 404);
            }

            // Ambil mata kuliah berdasarkan semester dan kurikulum
            $mataKuliah = MataKuliah::where('semester_rekomendasi', $semester)
                ->where('id_kurikulum', $idKurikulum)
                ->get(['id', 'kode_mk', 'nama_mk', 'teori', 'praktikum', 'klinik', 'sks']);

            if ($mataKuliah->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada mata kuliah ditemukan untuk semester ini.'
                ], 404);
            }

            // Ambil data prodi dan kurikulum untuk dropdown
            $prodi = Prodi::all(['id', 'nama_prodi']);
            $kurikulumList = Kurikulum::where('id_prodi', $kurikulum->id_prodi)->get(['id', 'id_prodi', 'nama_kurikulum']);

            return response()->json([
                'success' => true,
                'data' => [
                    'mata_kuliah' => $mataKuliah,
                    'prodi' => $prodi,
                    'kurikulum' => $kurikulumList,
                    'selected_prodi' => $kurikulum->id_prodi,
                    'selected_kurikulum' => $idKurikulum,
                    'semester' => $semester
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * Bisa digunakan untuk mengupdate dan membuat data baru.
     */
    public function update(Request $request, $semester): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'id_kurikulum' => 'required|exists:kurikulum,id',
                'semester_rekomendasi' => 'required|integer|min:1|max:14',
                'mata_kuliah' => 'required|array|min:1',
                'mata_kuliah.*.id' => 'nullable|exists:mata_kuliah,id', // ID boleh null untuk create
                'mata_kuliah.*.kode_mk' => 'required|string|max:20',
                'mata_kuliah.*.nama_mk' => 'required|string|max:255',
                'mata_kuliah.*.teori' => 'required|integer|min:0',
                'mata_kuliah.*.praktikum' => 'required|integer|min:0',
                'mata_kuliah.*.klinik' => 'required|integer|min:0',
            ]);

            // Opsional: Validasi bahwa kurikulum terkait dengan prodi
            $kurikulum = Kurikulum::where('id', $request->id_kurikulum)
                ->where('id_prodi', $request->id_prodi)
                ->first();

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak sesuai dengan prodi yang dipilih.'
                ], 422);
            }

            $updatedMataKuliah = [];
            $createdMataKuliah = [];
            $totalSKSSemester = 0;

            foreach ($request->mata_kuliah as $mk) {
                // Hitung SKS
                $sks = (int)$mk['teori'] + (int)$mk['praktikum'] + (int)$mk['klinik'];

                // Validasi SKS
                if ($sks < 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total SKS per mata kuliah (teori + praktikum + klinik) harus minimal 1.'
                    ], 422);
                }

                if (isset($mk['id']) && !empty($mk['id'])) {
                    // Update existing record
                    $mataKuliah = MataKuliah::find($mk['id']);

                    if (!$mataKuliah) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Mata Kuliah dengan ID ' . $mk['id'] . ' tidak ditemukan.'
                        ], 404);
                    }

                    // Pastikan mata kuliah ini benar-benar milik kurikulum ini
                    if ($mataKuliah->id_kurikulum !== $request->id_kurikulum) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Akses ditolak: Mata Kuliah tidak sesuai dengan kurikulum yang dipilih.'
                        ], 403);
                    }

                    $mataKuliah->update([
                        'kode_mk' => $mk['kode_mk'],
                        'nama_mk' => $mk['nama_mk'],
                        'teori' => $mk['teori'],
                        'praktikum' => $mk['praktikum'],
                        'klinik' => $mk['klinik'],
                        'sks' => $sks,
                        'semester_rekomendasi' => $request->semester_rekomendasi,
                    ]);

                    $updatedMataKuliah[] = $mataKuliah;
                } else {
                    // Create new record
                    $mataKuliahData = [
                        'kode_mk' => $mk['kode_mk'],
                        'nama_mk' => $mk['nama_mk'],
                        'teori' => $mk['teori'],
                        'praktikum' => $mk['praktikum'],
                        'klinik' => $mk['klinik'],
                        'sks' => $sks,
                        'semester_rekomendasi' => $request->semester_rekomendasi,
                        'id_kurikulum' => $request->id_kurikulum,
                    ];

                    // Validasi unique kode_mk untuk create
                    $existing = MataKuliah::where('kode_mk', $mataKuliahData['kode_mk'])->first();
                    if ($existing) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Kode MK ' . $mataKuliahData['kode_mk'] . ' sudah digunakan.'
                        ], 422);
                    }

                    $mataKuliah = MataKuliah::create($mataKuliahData);
                    $createdMataKuliah[] = $mataKuliah;
                }

                $totalSKSSemester += $sks;
            }

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah semester berhasil diperbarui dan/atau ditambahkan.',
                'data' => [
                    'updated' => $updatedMataKuliah,
                    'created' => $createdMataKuliah,
                ],
                'total_sks_semester' => $totalSKSSemester
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui atau menambahkan mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource(s) from storage.
     * Bisa menghapus semua mata kuliah dalam semester tertentu dari kurikulum tertentu.
     */
    public function destroy(Request $request, $semester): JsonResponse
    {
        try {
            $request->validate([
                'id_kurikulum' => 'required|exists:kurikulum,id',
            ]);

            $deletedCount = MataKuliah::where('semester_rekomendasi', $semester)
                ->where('id_kurikulum', $request->id_kurikulum)
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada mata kuliah ditemukan untuk semester ' . $semester . ' pada kurikulum ini.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus ' . $deletedCount . ' mata kuliah dari semester ' . $semester . '.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroysigle(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            $mataKuliah->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
