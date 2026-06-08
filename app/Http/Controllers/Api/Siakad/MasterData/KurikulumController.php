<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumInduk;
use App\Models\MasterData\MataKuliah;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KurikulumController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::with($this->defaultRelations())
                ->get()
                ->map(fn(Kurikulum $item) => $this->serializeKurikulum($item));

            return response()->json([
                'success' => true,
                'message' => 'Data struktur kurikulum berhasil diambil',
                'data' => $kurikulum,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data struktur kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function matakuliahByProdi(string $id_kurikulum): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::findOrFail($id_kurikulum);
            $idProdi = $kurikulum->id_prodi;

            $mataKuliah = MataKuliah::select('id', 'kode_mk', 'nama_mk', 'sks', 'sks_tatap_muka', 'sks_praktikum', 'sks_praktek_lapangan', 'sks_simulasi')
                ->where('id_prodi', $idProdi)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data mata kuliah berhasil diambil',
                'data' => [
                    'matakuliah' => $mataKuliah
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

    public function kurikulumByProdi(string $id_kurikulum): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::findOrFail($id_kurikulum);
            $idProdi = $kurikulum->id_prodi;

            $kurikulumList = Kurikulum::with([
                'kurikulumInduk:id,id_prodi,id_jenis_kurikulum,nama_kurikulum,tahun_kurikulum,kode_kurikulum,is_aktif',
                'kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            ])
                ->select('id', 'id_kurikulum_induk', 'nama_struktur_mk')
                ->where('id_prodi', $idProdi)
                ->get()
                ->map(function (Kurikulum $item) {
                    return [
                        'id' => $item->id,
                        'jenis_entitas' => 'struktur_operasional',
                        'id_kurikulum_induk' => $item->id_kurikulum_induk,
                        'nama_struktur_mk' => $item->nama_struktur_mk,
                        'nama_kurikulum' => $item->nama_kurikulum,
                        'kurikulum_induk' => $this->serializeKurikulumInduk($item),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data struktur kurikulum berhasil diambil',
                'data' => [
                    'kurikulum' => $kurikulumList
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::select([
                'id',
                'id_prodi',
                'id_kurikulum_induk',
                'nama_struktur_mk',
                'id_semester',
                'jumlah_sks_lulus',
                'jumlah_sks_wajib',
                'jumlah_sks_pilihan',
            ])
                ->with([
                    'prodi:id,jenjang_pendidikan,nama_prodi,kode_prodi',
                    'kurikulumInduk:id,id_prodi,id_jenis_kurikulum,nama_kurikulum,tahun_kurikulum,kode_kurikulum,is_aktif',
                    'kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
                    'semesterMulai:id,id_tahun_akademik,nama_semester',
                    'semesterMulai.tahunAkademik:id,tahun_akademik',
                ])
                ->findOrFail($id);

            $mataKuliahDiKurikulum = DB::table('kurikulum_mata_kuliah as kmk')
                ->join('mata_kuliah as mk', 'kmk.id_mata_kuliah', '=', 'mk.id')
                ->select(
                    'mk.id',
                    'mk.kode_mk',
                    'mk.nama_mk',
                    'mk.sks',
                    'mk.sks_tatap_muka',
                    'mk.sks_praktikum',
                    'mk.sks_praktek_lapangan',
                    'mk.sks_simulasi',
                    'kmk.semester_ke',
                    'kmk.status_mk',
                    'kmk.is_wajib'
                )
                ->where('kmk.id_kurikulum', $id)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kode_mk' => $item->kode_mk,
                        'nama_mk' => $item->nama_mk,
                        'sks' => $item->sks,
                        'sks_tatap_muka' => $item->sks_tatap_muka,
                        'sks_praktikum' => $item->sks_praktikum,
                        'sks_praktek_lapangan' => $item->sks_praktek_lapangan,
                        'sks_simulasi' => $item->sks_simulasi,
                        'pivot' => [
                            'semester_ke' => $item->semester_ke,
                            'status_mk' => $item->status_mk,
                            'is_wajib' => $item->is_wajib,
                        ]
                    ];
                })
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $this->serializeKurikulum($kurikulum, $mataKuliahDiKurikulum),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (!$request->filled('nama_struktur_mk') && $request->filled('nama_kurikulum')) {
                $request->merge([
                    'nama_struktur_mk' => $request->input('nama_kurikulum'),
                ]);
            }

            $validatedData = $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'id_kurikulum_induk' => 'required|exists:kurikulum_induk,id',
                'nama_struktur_mk' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('kurikulum')
                        ->where(function ($query) use ($request) {
                            return $query->where('id_prodi', $request->id_prodi);
                        })
                ],
                'id_semester' => 'nullable|exists:semester,id',
                'jumlah_sks_wajib' => 'nullable|integer|min:0',
                'jumlah_sks_pilihan' => 'nullable|integer|min:0',
            ]);

            $kurikulumInduk = KurikulumInduk::findOrFail($validatedData['id_kurikulum_induk']);
            if ($kurikulumInduk->id_prodi !== $validatedData['id_prodi']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun kurikulum harus berasal dari program studi yang sama.',
                ], 422);
            }

            $validatedData['jumlah_sks_lulus'] = ($validatedData['jumlah_sks_wajib'] ?? 0)
                + ($validatedData['jumlah_sks_pilihan'] ?? 0);

            $kurikulum = Kurikulum::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Struktur kurikulum berhasil ditambahkan',
                'data' => $this->serializeKurikulum($kurikulum->fresh($this->defaultRelations()))
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            if (!$request->filled('nama_struktur_mk') && $request->filled('nama_kurikulum')) {
                $request->merge([
                    'nama_struktur_mk' => $request->input('nama_kurikulum'),
                ]);
            }

            $kurikulum = Kurikulum::findOrFail($id);

            $validatedData = $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'id_kurikulum_induk' => 'required|exists:kurikulum_induk,id',
                'nama_struktur_mk' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('kurikulum')
                        ->where(function ($query) use ($request) {
                            return $query->where('id_prodi', $request->id_prodi);
                        })
                        ->ignore($kurikulum->id)
                ],
                'id_semester' => 'nullable|exists:semester,id',
                'jumlah_sks_wajib' => 'nullable|integer|min:0',
                'jumlah_sks_pilihan' => 'nullable|integer|min:0',
            ]);

            $kurikulumInduk = KurikulumInduk::findOrFail($validatedData['id_kurikulum_induk']);
            if ($kurikulumInduk->id_prodi !== $validatedData['id_prodi']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun kurikulum harus berasal dari program studi yang sama.',
                ], 422);
            }

            $validatedData['jumlah_sks_lulus'] = ($validatedData['jumlah_sks_wajib'] ?? 0)
                + ($validatedData['jumlah_sks_pilihan'] ?? 0);

            $kurikulum->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Struktur kurikulum berhasil diperbarui',
                'data' => $this->serializeKurikulum($kurikulum->fresh($this->defaultRelations()))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $kurikulum = Kurikulum::findOrFail($id);

            $kurikulum->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Struktur kurikulum dan data mata kuliah terkait berhasil dihapus',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function tambahMataKuliahManual(Request $request, string $id_kurikulum): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'mata_kuliah' => 'required|array|min:1',
                'mata_kuliah.*.id_mata_kuliah' => 'required|exists:mata_kuliah,id',
                'mata_kuliah.*.semester_ke' => 'nullable|integer',
                'mata_kuliah.*.is_wajib' => 'nullable|in:0,1',
            ]);

            $kurikulum = Kurikulum::findOrFail($id_kurikulum);

            foreach ($request->mata_kuliah as $mk) {
                $isWajibBool = isset($mk['is_wajib']) ? (bool) $mk['is_wajib'] : false;
                $statusMk = $isWajibBool ? 'wajib' : 'pilihan';

                $exists = DB::table('kurikulum_mata_kuliah')
                    ->where('id_kurikulum', $kurikulum->id)
                    ->where('id_mata_kuliah', $mk['id_mata_kuliah'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('kurikulum_mata_kuliah')->insert([
                    'id' => (string) Str::uuid(),
                    'id_kurikulum' => $kurikulum->id,
                    'id_mata_kuliah' => $mk['id_mata_kuliah'],
                    'semester_ke' => $mk['semester_ke'] ?? null,
                    'status_mk' => $statusMk,
                    'is_wajib' => $isWajibBool,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $rekap = DB::table('kurikulum_mata_kuliah as kmk')
                ->join('mata_kuliah as mk', 'kmk.id_mata_kuliah', '=', 'mk.id')
                ->selectRaw('
                SUM(CASE WHEN kmk.is_wajib = 1 THEN mk.sks ELSE 0 END) as wajib,
                SUM(CASE WHEN kmk.is_wajib = 0 THEN mk.sks ELSE 0 END) as pilihan
            ')
                ->where('kmk.id_kurikulum', $kurikulum->id)
                ->first();

            if (
                ($rekap->wajib ?? 0) > $kurikulum->jumlah_sks_wajib ||
                ($rekap->pilihan ?? 0) > $kurikulum->jumlah_sks_pilihan
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah SKS wajib atau pilihan melebihi batas kurikulum.'
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mata kuliah berhasil ditambahkan.',
                'data' => $kurikulum->fresh()->load('mataKuliah'),
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan mata kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function tambahMataKuliahCheckbox(Request $request, string $id_kurikulum): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'selected_mk' => 'required|array|min:1',
                'selected_mk.*' => 'exists:mata_kuliah,id',
                'semester_ke' => 'required|array',
                'semester_ke.*' => 'nullable|integer|min:1|max:8',
                'is_wajib' => 'nullable|array',
                'is_wajib.*' => 'in:1',
            ]);

            $kurikulum = Kurikulum::findOrFail($id_kurikulum);
            $selected = $request->selected_mk;

            $existing = DB::table('kurikulum_mata_kuliah')
                ->where('id_kurikulum', $kurikulum->id)
                ->whereIn('id_mata_kuliah', $selected)
                ->pluck('id_mata_kuliah')
                ->toArray();

            $insertData = [];

            foreach ($selected as $id_mk) {
                if (in_array($id_mk, $existing)) {
                    continue;
                }

                $semester = isset($request->semester_ke[$id_mk])
                    ? (int) $request->semester_ke[$id_mk]
                    : null;

                $isWajib = isset($request->is_wajib[$id_mk]) ? 1 : 0;

                $insertData[] = [
                    'id' => (string) Str::uuid(),
                    'id_kurikulum' => $kurikulum->id,
                    'id_mata_kuliah' => $id_mk,
                    'semester_ke' => $semester,
                    'status_mk' => $isWajib ? 'wajib' : 'pilihan',
                    'is_wajib' => $isWajib,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($insertData)) {
                DB::table('kurikulum_mata_kuliah')->insert($insertData);
            }

            $rekap = DB::table('kurikulum_mata_kuliah as kmk')
                ->join('mata_kuliah as mk', 'kmk.id_mata_kuliah', '=', 'mk.id')
                ->selectRaw('
                SUM(CASE WHEN kmk.is_wajib = 1 THEN mk.sks ELSE 0 END) as wajib,
                SUM(CASE WHEN kmk.is_wajib = 0 THEN mk.sks ELSE 0 END) as pilihan
            ')
                ->where('kmk.id_kurikulum', $kurikulum->id)
                ->first();

            if (
                ($rekap->wajib ?? 0) > $kurikulum->jumlah_sks_wajib ||
                ($rekap->pilihan ?? 0) > $kurikulum->jumlah_sks_pilihan
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah SKS melebihi batas kurikulum'
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mata kuliah berhasil ditambahkan',
                'total_insert' => count($insertData)
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMataKuliah(Request $request, string $id_kurikulum, string $id_mata_kuliah): JsonResponse
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'semester_ke' => 'nullable|integer',
                'is_wajib' => 'nullable|string|in:0,1',
            ]);

            $kurikulum = Kurikulum::findOrFail($id_kurikulum);

            $isWajib = $request->input('is_wajib');
            $isWajibBool = null;

            if (!is_null($isWajib)) {
                $isWajibBool = filter_var($isWajib, FILTER_VALIDATE_BOOLEAN);
            }

            $statusMk = null;
            if (!is_null($isWajibBool)) {
                $statusMk = $isWajibBool ? 'wajib' : 'pilihan';
            }

            $dataToUpdate = [
                'semester_ke' => $request->input('semester_ke'),
                'status_mk' => $statusMk,
                'is_wajib' => $isWajibBool,
                'updated_at' => now(),
            ];

            $dataToUpdate = array_filter($dataToUpdate, fn($value) => !is_null($value));

            DB::table('kurikulum_mata_kuliah')
                ->where('id_kurikulum', $id_kurikulum)
                ->where('id_mata_kuliah', $id_mata_kuliah)
                ->update($dataToUpdate);

            DB::commit();

            return response()->json([
                'message' => 'Data mata kuliah berhasil diperbarui.',
                'data' => $kurikulum->fresh()->load('mataKuliah'),
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui mata kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function hapusMataKuliah(Request $request, string $id_kurikulum, string $id_mata_kuliah): JsonResponse
    {
        DB::beginTransaction();
        try {
            $kurikulum = Kurikulum::findOrFail($id_kurikulum);

            DB::table('kurikulum_mata_kuliah')
                ->where('id_kurikulum', $id_kurikulum)
                ->where('id_mata_kuliah', $id_mata_kuliah)
                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Mata kuliah berhasil dihapus dari kurikulum.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus mata kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cloneMataKuliah(Request $request, string $id_kurikulum_tujuan, string $id_kurikulum_asal): JsonResponse
    {
        DB::beginTransaction();
        try {
            $kurikulumTujuan = Kurikulum::findOrFail($id_kurikulum_tujuan);

            $mataKuliahAsal = DB::table('kurikulum_mata_kuliah as kmk')
                ->join('mata_kuliah as mk', 'kmk.id_mata_kuliah', '=', 'mk.id')
                ->select(
                    'mk.id as id_mata_kuliah',
                    'mk.kode_mk',
                    'mk.nama_mk',
                    'kmk.semester_ke',
                    'kmk.status_mk',
                    'kmk.is_wajib'
                )
                ->where('kmk.id_kurikulum', $id_kurikulum_asal)
                ->get();

            foreach ($mataKuliahAsal as $mk) {
                $isWajib = $mk->is_wajib;
                $isWajibBool = null;

                if (!is_null($isWajib)) {
                    $isWajibBool = filter_var($isWajib, FILTER_VALIDATE_BOOLEAN);
                }

                $statusMk = null;
                if (!is_null($isWajibBool)) {
                    $statusMk = $isWajibBool ? 'wajib' : 'pilihan';
                }

                $dataToInsert = [
                    'id' => (string) Str::uuid(),
                    'id_kurikulum' => $kurikulumTujuan->id,
                    'id_mata_kuliah' => $mk->id_mata_kuliah,
                    'semester_ke' => $mk->semester_ke,
                    'status_mk' => $statusMk,
                    'is_wajib' => $isWajibBool,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $exists = DB::table('kurikulum_mata_kuliah')
                    ->where('id_kurikulum', $kurikulumTujuan->id)
                    ->where('id_mata_kuliah', $mk->id_mata_kuliah)
                    ->exists();

                if (!$exists) {
                    DB::table('kurikulum_mata_kuliah')->insert($dataToInsert);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Mata kuliah berhasil dikloning ke kurikulum tujuan.',
                'data' => $kurikulumTujuan->fresh(),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengkloning mata kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function defaultRelations(): array
    {
        return [
            'prodi:id,nama_prodi,jenjang_pendidikan,kode_prodi',
            'kurikulumInduk:id,id_prodi,id_jenis_kurikulum,nama_kurikulum,tahun_kurikulum,kode_kurikulum,is_aktif',
            'kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            'semesterMulai.tahunAkademik:id,tahun_akademik',
            'mataKuliah:id,sks',
        ];
    }

    private function serializeKurikulum(Kurikulum $item, ?array $mataKuliah = null): array
    {
        $totalWajib = $item->relationLoaded('mataKuliah')
            ? $item->mataKuliah->where('pivot.is_wajib', 1)->sum('sks')
            : null;
        $totalPilihan = $item->relationLoaded('mataKuliah')
            ? $item->mataKuliah->where('pivot.is_wajib', 0)->sum('sks')
            : null;
        $semesterMulai = $item->semesterMulai
            ? $item->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $item->semesterMulai->nama_semester
            : null;

        return [
            'id' => $item->id,
            'jenis_entitas' => 'struktur_operasional',
            'id_prodi' => $item->id_prodi,
            'id_kurikulum_induk' => $item->id_kurikulum_induk,
            'id_semester' => $item->id_semester,
            'nama_struktur_mk' => $item->nama_struktur_mk,
            'nama_kurikulum' => $item->nama_kurikulum,
            'nama_kurikulum_induk' => $item->nama_kurikulum_induk,
            'keterangan_kurikulum_induk' => $item->nama_kurikulum_induk,
            'jumlah_sks_lulus' => $item->jumlah_sks_lulus,
            'jumlah_sks_wajib' => $item->jumlah_sks_wajib,
            'jumlah_sks_pilihan' => $item->jumlah_sks_pilihan,
            'jumlah_sks_wajib_mk' => $totalWajib,
            'jumlah_sks_pilihan_mk' => $totalPilihan,
            'prodi' => $item->prodi
                ? "({$item->prodi->jenjang_pendidikan}) {$item->prodi->nama_prodi}"
                : null,
            'semester_mulai' => $semesterMulai,
            'mulai_berlaku' => $semesterMulai,
            'kurikulum_induk' => $this->serializeKurikulumInduk($item),
            'struktur_operasional' => [
                'id' => $item->id,
                'nama_struktur_mk' => $item->nama_struktur_mk,
                'id_semester' => $item->id_semester,
                'semester_mulai' => $semesterMulai,
                'mulai_berlaku' => $semesterMulai,
            ],
            'mata_kuliah' => $mataKuliah,
        ];
    }

    private function serializeKurikulumInduk(Kurikulum $item): ?array
    {
        if (!$item->kurikulumInduk) {
            return null;
        }

        return [
            'id' => $item->kurikulumInduk->id,
            'nama_kurikulum' => $item->kurikulumInduk->nama_kurikulum,
            'keterangan' => $item->kurikulumInduk->nama_kurikulum,
            'kode_kurikulum' => $item->kurikulumInduk->kode_kurikulum,
            'tahun_kurikulum' => $item->kurikulumInduk->tahun_kurikulum,
            'is_aktif' => $item->kurikulumInduk->is_aktif,
            'jenis_kurikulum' => $item->kurikulumInduk->jenisKurikulum ? [
                'id' => $item->kurikulumInduk->jenisKurikulum->id,
                'kode_jenis' => $item->kurikulumInduk->jenisKurikulum->kode_jenis,
                'nama_jenis_kurikulum' => $item->kurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
            ] : null,
        ];
    }
}
