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
            // Ambil semua data kelas MK dengan relasi
            $kelasMks = KelasMk::with([
                'mataKuliah:id,kode_mk,nama_mk,sks,semester_rekomendasi,id_kurikulum',
                'mataKuliah.kurikulum:id,nama_kurikulum,id_prodi',
                'mataKuliah.kurikulum.prodi:id,nama_prodi',
                'kelasPararel:id,nama_kelas,id_prodi',
                'semester:id,nama_semester,id_tahun_akademik',
                'semester.tahunAkademik:id,tahun_akademik,status_aktif',
                'jenisKelas:id,nama_kelas'
            ])->get();

            // Format data TANPA grouping
            $formattedData = $kelasMks->map(function ($kelasMk) {
                return [
                    'id' => $kelasMk->id,
                    'kode_kelas_mk' => $kelasMk->kode_kelas_mk,
                    'kuota' => $kelasMk->kuota,

                    // Mata Kuliah (TUNGGAL)
                    'mata_kuliah' => [
                        'id' => $kelasMk->mataKuliah->id ?? null,
                        'kode_mk' => $kelasMk->mataKuliah->kode_mk ?? null,
                        'nama_mk' => $kelasMk->mataKuliah->nama_mk ?? null,
                        'sks' => $kelasMk->mataKuliah->sks ?? 0,
                        'semester_rekomendasi' => $kelasMk->mataKuliah->semester_rekomendasi ?? null
                    ],

                    // Kelas Pararel
                    'kelas_pararel' => [
                        'id' => $kelasMk->kelasPararel->id ?? null,
                        'nama_kelas' => $kelasMk->kelasPararel->nama_kelas ?? null,
                        'id_prodi' => $kelasMk->kelasPararel->id_prodi ?? null
                    ],

                    // Semester & Tahun Akademik
                    'semester' => [
                        'id' => $kelasMk->semester->id ?? null,
                        'nama_semester' => $kelasMk->semester->nama_semester ?? null,
                        'tahun_akademik' => [
                            'id' => $kelasMk->semester->tahunAkademik->id ?? null,
                            'tahun_akademik' => $kelasMk->semester->tahunAkademik->tahun_akademik ?? null,
                            'status_aktif' => $kelasMk->semester->tahunAkademik->status_aktif ?? null
                        ]
                    ],

                    // Jenis Kelas
                    'jenis_kelas' => [
                        'id' => $kelasMk->jenisKelas->id ?? null,
                        'nama_kelas' => $kelasMk->jenisKelas->nama_kelas ?? null
                    ],

                    // Prodi
                    'prodi' => [
                        'id' => $kelasMk->mataKuliah->kurikulum->prodi->id ?? null,
                        'nama_prodi' => $kelasMk->mataKuliah->kurikulum->prodi->nama_prodi ?? null
                    ],

                    // Kurikulum
                    'kurikulum' => [
                        'id' => $kelasMk->mataKuliah->kurikulum->id ?? null,
                        'nama_kurikulum' => $kelasMk->mataKuliah->kurikulum->nama_kurikulum ?? null
                    ]
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Kelas Mata Kuliah',
                'data' => $formattedData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            // Jika tidak ada parameter nama_prodi, tampilkan hanya dropdown prodi
            $selectedProdiName = $request->input('nama_prodi');

            if (!$selectedProdiName) {
                // Hanya tampilkan daftar prodi untuk dipilih
                $prodis = Prodi::select('id', 'nama_prodi')->get();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'prodi_list' => $prodis,
                        'prodi' => [],
                        'jenis_kelas' => [],
                        'tahun_akademik' => null,
                        'semester' => null
                    ],
                    'message' => 'Silakan pilih program studi terlebih dahulu'
                ]);
            }

            // Validasi apakah prodi yang dipilih valid berdasarkan nama_prodi
            $prodiExists = Prodi::where('nama_prodi', $selectedProdiName)->exists();
            if (!$prodiExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program studi tidak ditemukan'
                ], 404);
            }

            // Dapatkan ID prodi berdasarkan nama_prodi untuk digunakan dalam query
            $selectedProdiId = Prodi::where('nama_prodi', $selectedProdiName)->value('id');

            // Ambil data mata kuliah dengan relasi kurikulum dan prodi (hanya untuk prodi yang dipilih)
            $mataKuliahsQuery = MataKuliah::select('id', 'id_kurikulum', 'kode_mk', 'nama_mk', 'semester_rekomendasi', 'sks', 'teori', 'praktikum', 'klinik')
                ->whereHas('kurikulum', function ($query) use ($selectedProdiId) {
                    $query->where('id_prodi', $selectedProdiId);
                });

            // Filter berdasarkan semester jika parameter 'semester' disediakan
            $selectedSemester = $request->input('semester');
            if ($selectedSemester !== null) {
                $mataKuliahsQuery->where('semester_rekomendasi', (int)$selectedSemester);
            }

            $mataKuliahs = $mataKuliahsQuery
                ->with(['kurikulum:id,id_prodi,nama_kurikulum'])
                ->get();

            // Ambil data kelas pararel hanya untuk prodi yang dipilih
            $kelasPararels = KelasPararel::select('id', 'id_prodi', 'nama_kelas')
                ->where('id_prodi', $selectedProdiId)
                ->get();

            // Ambil data prodi yang dipilih berdasarkan nama_prodi
            $prodis = Prodi::select('id', 'nama_prodi')
                ->where('nama_prodi', $selectedProdiName)
                ->get();

            // Ambil data jenis kelas
            $jenisKelas = JenisKelas::select('id', 'nama_kelas')->get();

            // Ambil data kurikulum untuk prodi yang dipilih saja
            $kurikulums = Kurikulum::select('id', 'id_prodi', 'nama_kurikulum')
                ->where('id_prodi', $selectedProdiId)
                ->where('status', true)
                ->get();

            // Ambil tahun akademik aktif beserta satu semester aktif saja
            $tahunAkademikAktif = TahunAkademik::select('id', 'tahun_akademik', 'status_aktif')
                ->with(['semester' => function ($query) {
                    $query->select('id', 'nama_semester', 'id_tahun_akademik', 'status')
                        ->where('status', 'aktif')
                        ->limit(1);
                }])
                ->where('status_aktif', true)
                ->first();

            $semesterAktif = null;
            if ($tahunAkademikAktif && $tahunAkademikAktif->semester->count() > 0) {
                $semesterAktif = $tahunAkademikAktif->semester->first();
            }

            // Struktur nested: prodi -> kurikulum -> mata kuliah berdasarkan semester
            $dropdownStructure = [
                'prodi' => $prodis->map(function ($prodi) use ($kurikulums, $mataKuliahs, $kelasPararels, $selectedSemester) {
                    return [
                        'id' => $prodi->id,
                        'nama_prodi' => $prodi->nama_prodi,
                        'kurikulum' => $kurikulums
                            ->where('id_prodi', $prodi->id)
                            ->values()->map(function ($kurikulum) use ($mataKuliahs, $selectedSemester) {
                                // Filter mata kuliah berdasarkan kurikulum dan semester (jika ada filter)
                                $filteredMataKuliahs = $mataKuliahs
                                    ->where('id_kurikulum', $kurikulum->id);

                                if ($selectedSemester !== null) {
                                    // Jika semester difilter, tampilkan hanya di semester tertentu
                                    $mataKuliahBySemester = collect([
                                        (string)$selectedSemester => $filteredMataKuliahs
                                    ]);

                                    $structure = [
                                        'id' => $kurikulum->id,
                                        'nama_kurikulum' => $kurikulum->nama_kurikulum,
                                    ];

                                    $structure['mata_kuliah_by_semester'] = $mataKuliahBySemester
                                        ->map(function ($mks, $semester) {
                                            return [
                                                'semester' => (int)$semester,
                                                'jumlah_mk' => count($mks),
                                                'mata_kuliah' => $mks->values()->map(function ($mk) {
                                                    return [
                                                        'id' => $mk->id,
                                                        'kode_mk' => $mk->kode_mk,
                                                        'nama_mk' => $mk->nama_mk,
                                                        'sks' => $mk->sks ?? 0,
                                                        'id_kurikulum' => $mk->id_kurikulum,
                                                        'semester_rekomendasi' => $mk->semester_rekomendasi,
                                                        'teori' => $mk->teori ?? 0,
                                                        'praktikum' => $mk->praktikum ?? 0,
                                                        'klinik' => $mk->klinik ?? 0,
                                                    ];
                                                })
                                            ];
                                        })
                                        ->values()
                                        ->sortBy('semester')
                                        ->values();
                                } else {
                                    // Jika tidak ada filter semester, kelompokkan seperti sebelumnya
                                    $mataKuliahBySemester = $filteredMataKuliahs
                                        ->groupBy('semester_rekomendasi');

                                    $structure = [
                                        'id' => $kurikulum->id,
                                        'nama_kurikulum' => $kurikulum->nama_kurikulum,
                                    ];

                                    // Tambahkan mata_kuliah_by_semester jika ada data mata kuliah
                                    if ($mataKuliahBySemester->isNotEmpty()) {
                                        $structure['mata_kuliah_by_semester'] = $mataKuliahBySemester
                                            ->map(function ($mks, $semester) {
                                                return [
                                                    'semester' => (int)$semester,
                                                    'jumlah_mk' => count($mks),
                                                    'mata_kuliah' => $mks->values()->map(function ($mk) {
                                                        return [
                                                            'id' => $mk->id,
                                                            'kode_mk' => $mk->kode_mk,
                                                            'nama_mk' => $mk->nama_mk,
                                                            'sks' => $mk->sks ?? 0,
                                                            'id_kurikulum' => $mk->id_kurikulum,
                                                            'semester_rekomendasi' => $mk->semester_rekomendasi,
                                                            'teori' => $mk->teori ?? 0,
                                                            'praktikum' => $mk->praktikum ?? 0,
                                                            'klinik' => $mk->klinik ?? 0,
                                                        ];
                                                    })
                                                ];
                                            })
                                            ->values()
                                            ->sortBy('semester')
                                            ->values();
                                    } else {
                                        // Jika tidak ada mata_kuliah_by_semester, tampilkan mata_kuliah biasa
                                        $structure['mata_kuliah'] = $filteredMataKuliahs
                                            ->values()->map(function ($mk) {
                                                return [
                                                    'id' => $mk->id,
                                                    'kode_mk' => $mk->kode_mk,
                                                    'nama_mk' => $mk->nama_mk,
                                                    'sks' => $mk->sks ?? 0,
                                                    'id_kurikulum' => $mk->id_kurikulum,
                                                    'semester_rekomendasi' => $mk->semester_rekomendasi,
                                                    'teori' => $mk->teori ?? 0,
                                                    'praktikum' => $mk->praktikum ?? 0,
                                                    'klinik' => $mk->klinik ?? 0,
                                                ];
                                            });
                                    }
                                }

                                return $structure;
                            }),
                        'kelas_pararel' => $kelasPararels
                            ->where('id_prodi', $prodi->id)
                            ->values()->map(function ($kp) {
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
                'semester' => $semesterAktif ? [
                    'id' => $semesterAktif->id,
                    'nama_semester' => $semesterAktif->nama_semester,
                    'id_tahun_akademik' => $semesterAktif->id_tahun_akademik,
                    'status' => $semesterAktif->status,
                ] : null
            ];

            return response()->json([
                'success' => true,
                'data' => $dropdownStructure,
                'filters_applied' => [
                    'nama_prodi' => $selectedProdiName,
                    'semester' => $selectedSemester
                ]
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

    public function edit(string $id, Request $request): JsonResponse
    {
        try {
            // =========================
            // Data Kelas MK
            // =========================
            $kelasMk = KelasMk::with([
                'semester.tahunAkademik',
                'mataKuliah:id,id_kurikulum,kode_mk,nama_mk,semester_rekomendasi,sks',
                'mataKuliah.kurikulum:id,id_prodi,nama_kurikulum',
                'kelasPararel:id,nama_kelas',
                'jenisKelas:id,nama_kelas',
            ])->find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan'
                ], 404);
            }

            // =========================
            // Tahun + Semester (BARU)
            // =========================
            $tahunSemester = null;
            if ($kelasMk->semester && $kelasMk->semester->tahunAkademik) {
                $tahunSemester = [
                    'id_semester' => $kelasMk->semester->id,
                    'label' => $kelasMk->semester->nama_semester
                        . ' ' .
                        $kelasMk->semester->tahunAkademik->tahun_akademik
                ];
            }

            // =========================
            // Prodi (Request > Existing)
            // =========================
            $selectedProdi = $request->input('nama_prodi')
                ?? $kelasMk->mataKuliah?->kurikulum?->prodi?->nama_prodi;

            $prodi = Prodi::select('id', 'nama_prodi')
                ->where('nama_prodi', $selectedProdi)
                ->firstOrFail();

            // =========================
            // Semester AUTO dari MK
            // =========================
            $currentMk = $kelasMk->mataKuliah;

            $semesterFilter = $request->filled('semester')
                ? (int) $request->semester
                : (int) $currentMk?->semester_rekomendasi;

            // =========================
            // Mata Kuliah (Filtered & Grouped)
            // =========================
            $mataKuliahs = MataKuliah::select(
                'id',
                'id_kurikulum',
                'kode_mk',
                'nama_mk',
                'semester_rekomendasi',
                'sks'
            )
                ->whereHas(
                    'kurikulum',
                    fn($q) =>
                    $q->where('id_prodi', $prodi->id)
                )
                ->when(
                    $semesterFilter,
                    fn($q) =>
                    $q->where('semester_rekomendasi', $semesterFilter)
                )
                ->get()
                ->groupBy([
                    'id_kurikulum',
                    fn($mk) => (int) $mk->semester_rekomendasi
                ]);

            // =========================
            // Dropdown Data
            // =========================
            $dropdown = [
                'kurikulum' => Kurikulum::select('id', 'nama_kurikulum')
                    ->where('id_prodi', $prodi->id)
                    ->where('status', true)
                    ->get()
                    ->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama_kurikulum,
                        'mata_kuliah_by_semester' =>
                        collect($mataKuliahs[$k->id] ?? [])
                            ->map(fn($mks, $semester) => [
                                'semester' => (int) $semester,
                                'jumlah_mk' => $mks->count(),
                                'mata_kuliah' => $mks->values()
                            ])
                            ->values()
                    ]),
                'kelas_pararel' => KelasPararel::select('id', 'nama_kelas')
                    ->where('id_prodi', $prodi->id)
                    ->get(),
                'jenis_kelas' => JenisKelas::select('id', 'nama_kelas')->get()
            ];

            // =========================
            // RESPONSE
            // =========================
            return response()->json([
                'success' => true,
                'data' => [
                    'tahun_semester' => $tahunSemester, // 🔥 BARU
                    'kelas_mk' => [
                        'id' => $kelasMk->id,
                        'id_mk' => $kelasMk->id_mk,
                        'id_semester' => $kelasMk->id_semester,
                        'kode' => $kelasMk->kode_kelas_mk,
                        'kuota' => $kelasMk->kuota,
                        'id_kelas_pararel' => $kelasMk->id_kelas_pararel,
                        'id_jenis_kelas' => $kelasMk->id_jenis_kelas,
                        'semester_rekomendasi' => $semesterFilter
                    ],
                    'filters' => [
                        'nama_prodi' => $selectedProdi,
                        'semester' => $semesterFilter
                    ],
                    'dropdown' => $dropdown
                ]
            ]);
        } catch (\Throwable $e) {
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
