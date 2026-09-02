<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
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
                'semesterMulai.tahunAkademik:id,tahun_akademik',
            ])
                ->select('id', 'id_prodi', 'id_semester', 'nama_struktur_mk', 'jumlah_sks_lulus')
                ->where('id_prodi', $idProdi)
                ->get()
                ->map(function (Kurikulum $item) {
                    $semesterMulai = $item->semesterMulai?->tahunAkademik
                        ? trim($item->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $item->semesterMulai->nama_semester)
                        : null;

                    return [
                        'id' => $item->id,
                        'jenis_entitas' => 'struktur_operasional',
                        'nama_struktur_mk' => $item->nama_struktur_mk,
                        'nama_kurikulum' => $item->nama_kurikulum,
                        'mulai_berlaku' => $semesterMulai,
                        'struktur_operasional' => [
                            'id' => $item->id,
                            'nama_struktur_mk' => $item->nama_struktur_mk,
                            'id_semester' => $item->id_semester,
                            'mulai_berlaku' => $semesterMulai,
                        ],
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

            /*
        |--------------------------------------------------------------------------
        | Ambil data kurikulum
        |--------------------------------------------------------------------------
        */

            $kurikulum = Kurikulum::select([
                'id',
                'id_prodi',
                'nama_struktur_mk',
                'id_semester',
                'jumlah_sks_lulus',
                'jumlah_sks_wajib',
                'jumlah_sks_pilihan',
            ])
                ->with([
                    'prodi:id,jenjang_pendidikan,nama_prodi,kode_prodi',

                    'semesterMulai:id,id_tahun_akademik,nama_semester',

                    'semesterMulai.tahunAkademik:id,tahun_akademik',
                ])
                ->findOrFail($id);


            /*
        |--------------------------------------------------------------------------
        | Ambil mata kuliah dalam kurikulum
        |--------------------------------------------------------------------------
        */

            $mataKuliahDiKurikulum = DB::table(
                'kurikulum_mata_kuliah as kmk'
            )
                ->join(
                    'mata_kuliah as mk',
                    'kmk.id_mata_kuliah',
                    '=',
                    'mk.id'
                )
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
                ->where(
                    'kmk.id_kurikulum',
                    $id
                )
                ->get()
                ->map(function ($item) {

                    return [
                        'id' => $item->id,

                        'kode_mk' => $item->kode_mk,

                        'nama_mk' => $item->nama_mk,

                        'sks' => (int) $item->sks,

                        'sks_tatap_muka' =>
                        $item->sks_tatap_muka,

                        'sks_praktikum' =>
                        $item->sks_praktikum,

                        'sks_praktek_lapangan' =>
                        $item->sks_praktek_lapangan,

                        'sks_simulasi' =>
                        $item->sks_simulasi,

                        'pivot' => [
                            'semester_ke' =>
                            $item->semester_ke,

                            'status_mk' =>
                            $item->status_mk,

                            'is_wajib' =>
                            (int) $item->is_wajib,
                        ],
                    ];
                })
                ->toArray();


            /*
        |--------------------------------------------------------------------------
        | Hitung total SKS
        |--------------------------------------------------------------------------
        */

            $totalSksWajib = collect(
                $mataKuliahDiKurikulum
            )
                ->filter(function ($mk) {
                    return (int) (
                        $mk['pivot']['is_wajib'] ?? 0
                    ) === 1;
                })
                ->sum(function ($mk) {
                    return (int) ($mk['sks'] ?? 0);
                });


            $totalSksPilihan = collect(
                $mataKuliahDiKurikulum
            )
                ->filter(function ($mk) {
                    return (int) (
                        $mk['pivot']['is_wajib'] ?? 0
                    ) === 0;
                })
                ->sum(function ($mk) {
                    return (int) ($mk['sks'] ?? 0);
                });


            /*
        |--------------------------------------------------------------------------
        | Target SKS
        |--------------------------------------------------------------------------
        */

            $targetSksWajib = (int) (
                $kurikulum->jumlah_sks_wajib ?? 0
            );

            $targetSksPilihan = (int) (
                $kurikulum->jumlah_sks_pilihan ?? 0
            );


            /*
        |--------------------------------------------------------------------------
        | Status SKS Wajib
        |--------------------------------------------------------------------------
        */

            if ($totalSksWajib < $targetSksWajib) {

                $statusSksWajib = 'kurang';
            } elseif ($totalSksWajib === $targetSksWajib) {

                $statusSksWajib = 'terpenuhi';
            } else {

                $statusSksWajib = 'lebih';
            }


            /*
        |--------------------------------------------------------------------------
        | Status SKS Pilihan
        |--------------------------------------------------------------------------
        */

            if ($totalSksPilihan < $targetSksPilihan) {

                $statusSksPilihan = 'kurang';
            } elseif ($totalSksPilihan === $targetSksPilihan) {

                $statusSksPilihan = 'terpenuhi';
            } else {

                $statusSksPilihan = 'lebih';
            }


            /*
        |--------------------------------------------------------------------------
        | Status Kurikulum
        |--------------------------------------------------------------------------
        */

            $statusKurikulum =
                $statusSksWajib === 'terpenuhi'
                &&
                $statusSksPilihan === 'terpenuhi'
                ? 'lengkap'
                : 'belum_lengkap';


            /*
        |--------------------------------------------------------------------------
        | Progress SKS
        |--------------------------------------------------------------------------
        */

            $progressSks = [

                'wajib' => [

                    'target' => $targetSksWajib,

                    'total' => $totalSksWajib,

                    'kekurangan' => max(
                        0,
                        $targetSksWajib - $totalSksWajib
                    ),

                    'kelebihan' => max(
                        0,
                        $totalSksWajib - $targetSksWajib
                    ),

                    'status' => $statusSksWajib,
                ],


                'pilihan' => [

                    'target' => $targetSksPilihan,

                    'total' => $totalSksPilihan,

                    'kekurangan' => max(
                        0,
                        $targetSksPilihan - $totalSksPilihan
                    ),

                    'kelebihan' => max(
                        0,
                        $totalSksPilihan - $targetSksPilihan
                    ),

                    'status' => $statusSksPilihan,
                ],


                'total' => [

                    'target' =>
                    $targetSksWajib
                        + $targetSksPilihan,

                    'terisi' =>
                    $totalSksWajib
                        + $totalSksPilihan,

                    'kekurangan' =>
                    max(
                        0,
                        (
                            $targetSksWajib
                            + $targetSksPilihan
                        )
                            -
                            (
                                $totalSksWajib
                                + $totalSksPilihan
                            )
                    ),
                ],


                'status' => $statusKurikulum,
            ];


            /*
        |--------------------------------------------------------------------------
        | Serialize
        |--------------------------------------------------------------------------
        */

            $data = $this->serializeKurikulum(
                $kurikulum,
                $mataKuliahDiKurikulum
            );


            /*
        |--------------------------------------------------------------------------
        | Tambahkan progress SKS
        |--------------------------------------------------------------------------
        */

            $data['progress_sks'] = $progressSks;


            /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => 'success',

                'data' => $data,

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

            $blockingRelations = [];

            $konversiAsalCount = DB::table('konversi_mata_kuliah')
                ->where('id_kurikulum_asal', $id)
                ->count();
            if ($konversiAsalCount > 0) {
                $blockingRelations[] = "konversi mata kuliah sebagai kurikulum asal ({$konversiAsalCount})";
            }

            $konversiTujuanCount = DB::table('konversi_mata_kuliah')
                ->where('id_kurikulum_tujuan', $id)
                ->count();
            if ($konversiTujuanCount > 0) {
                $blockingRelations[] = "konversi mata kuliah sebagai kurikulum tujuan ({$konversiTujuanCount})";
            }

            if (!empty($blockingRelations)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Struktur kurikulum tidak dapat dihapus karena masih terhubung dengan ' . implode(', ', $blockingRelations) . '.'
                ], 422);
            }

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

    public function tambahMataKuliahManual(
        Request $request,
        string $id_kurikulum
    ): JsonResponse {
        try {

            $validated = $request->validate([
                'mata_kuliah' => 'required|array|min:1',

                'mata_kuliah.*.id_mata_kuliah' => [
                    'required',
                    'exists:mata_kuliah,id',
                ],

                'mata_kuliah.*.semester_ke' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'mata_kuliah.*.is_wajib' => [
                    'nullable',
                    'in:0,1',
                ],
            ]);

            DB::beginTransaction();

            /*
        |--------------------------------------------------------------------------
        | Ambil kurikulum
        |--------------------------------------------------------------------------
        */

            $kurikulum = Kurikulum::findOrFail($id_kurikulum);

            $targetWajib = (int) ($kurikulum->jumlah_sks_wajib ?? 0);
            $targetPilihan = (int) ($kurikulum->jumlah_sks_pilihan ?? 0);


            /*
        |--------------------------------------------------------------------------
        | Ambil ID mata kuliah
        |--------------------------------------------------------------------------
        */

            $idMataKuliah = collect($validated['mata_kuliah'])
                ->pluck('id_mata_kuliah')
                ->unique()
                ->values()
                ->toArray();


            /*
        |--------------------------------------------------------------------------
        | Ambil data mata kuliah
        |--------------------------------------------------------------------------
        */

            $mataKuliahList = DB::table('mata_kuliah')
                ->whereIn('id', $idMataKuliah)
                ->get()
                ->keyBy('id');


            /*
        |--------------------------------------------------------------------------
        | Ambil mata kuliah yang sudah ada
        |--------------------------------------------------------------------------
        */

            $existingMataKuliah = DB::table('kurikulum_mata_kuliah')
                ->where('id_kurikulum', $kurikulum->id)
                ->whereIn('id_mata_kuliah', $idMataKuliah)
                ->pluck('id_mata_kuliah')
                ->toArray();


            /*
        |--------------------------------------------------------------------------
        | Hitung total SKS yang SUDAH ada
        |--------------------------------------------------------------------------
        */

            $rekap = DB::table('kurikulum_mata_kuliah as kmk')
                ->join(
                    'mata_kuliah as mk',
                    'kmk.id_mata_kuliah',
                    '=',
                    'mk.id'
                )
                ->where('kmk.id_kurikulum', $kurikulum->id)
                ->selectRaw('
                COALESCE(
                    SUM(
                        CASE
                            WHEN kmk.is_wajib = 1
                            THEN mk.sks
                            ELSE 0
                        END
                    ),
                    0
                ) AS wajib,

                COALESCE(
                    SUM(
                        CASE
                            WHEN kmk.is_wajib = 0
                            THEN mk.sks
                            ELSE 0
                        END
                    ),
                    0
                ) AS pilihan
            ')
                ->first();


            $totalWajib = (int) ($rekap->wajib ?? 0);
            $totalPilihan = (int) ($rekap->pilihan ?? 0);


            /*
        |--------------------------------------------------------------------------
        | Hasil proses
        |--------------------------------------------------------------------------
        */

            $berhasil = [];
            $ditolak = [];
            $duplikat = [];


            /*
        |--------------------------------------------------------------------------
        | Proses mata kuliah satu per satu
        |--------------------------------------------------------------------------
        */

            foreach ($validated['mata_kuliah'] as $item) {

                $idMk = $item['id_mata_kuliah'];


                /*
            |--------------------------------------------------------------------------
            | Cek duplicate
            |--------------------------------------------------------------------------
            */

                if (in_array($idMk, $existingMataKuliah)) {

                    $duplikat[] = [
                        'id_mata_kuliah' => $idMk,
                        'message' => 'Mata kuliah sudah terdapat pada kurikulum.',
                    ];

                    continue;
                }


                /*
            |--------------------------------------------------------------------------
            | Ambil mata kuliah
            |--------------------------------------------------------------------------
            */

                $mataKuliah = $mataKuliahList->get($idMk);

                if (!$mataKuliah) {
                    continue;
                }


                /*
            |--------------------------------------------------------------------------
            | Data mata kuliah
            |--------------------------------------------------------------------------
            */

                $sks = (int) $mataKuliah->sks;

                $isWajib = (int) ($item['is_wajib'] ?? 0) === 1;

                $statusMk = $isWajib
                    ? 'wajib'
                    : 'pilihan';


                /*
            |--------------------------------------------------------------------------
            | CEK SKS WAJIB
            |--------------------------------------------------------------------------
            */

                if ($isWajib) {

                    $totalSetelahDitambahkan = $totalWajib + $sks;

                    if ($totalSetelahDitambahkan > $targetWajib) {

                        $ditolak[] = [
                            'id_mata_kuliah' => $idMk,
                            'nama_mata_kuliah' => $mataKuliah->nama_mata_kuliah ?? null,
                            'sks' => $sks,
                            'jenis' => 'wajib',

                            'target_sks' => $targetWajib,
                            'sks_sekarang' => $totalWajib,
                            'sks_setelah_ditambahkan' => $totalSetelahDitambahkan,

                            'kelebihan' =>
                            $totalSetelahDitambahkan - $targetWajib,

                            'message' =>
                            'Mata kuliah tidak dapat ditambahkan karena jumlah SKS wajib akan melebihi target kurikulum.',
                        ];

                        continue;
                    }
                }


                /*
            |--------------------------------------------------------------------------
            | CEK SKS PILIHAN
            |--------------------------------------------------------------------------
            */

                if (!$isWajib) {

                    $totalSetelahDitambahkan = $totalPilihan + $sks;

                    if ($totalSetelahDitambahkan > $targetPilihan) {

                        $ditolak[] = [
                            'id_mata_kuliah' => $idMk,
                            'nama_mata_kuliah' => $mataKuliah->nama_mata_kuliah ?? null,
                            'sks' => $sks,
                            'jenis' => 'pilihan',

                            'target_sks' => $targetPilihan,
                            'sks_sekarang' => $totalPilihan,
                            'sks_setelah_ditambahkan' => $totalSetelahDitambahkan,

                            'kelebihan' =>
                            $totalSetelahDitambahkan - $targetPilihan,

                            'message' =>
                            'Mata kuliah tidak dapat ditambahkan karena jumlah SKS pilihan akan melebihi target kurikulum.',
                        ];

                        continue;
                    }
                }


                /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

                DB::table('kurikulum_mata_kuliah')->insert([
                    'id' => (string) Str::uuid(),

                    'id_kurikulum' => $kurikulum->id,

                    'id_mata_kuliah' => $idMk,

                    'semester_ke' => $item['semester_ke'] ?? null,

                    'status_mk' => $statusMk,

                    'is_wajib' => $isWajib,

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);


                /*
            |--------------------------------------------------------------------------
            | Update total
            |--------------------------------------------------------------------------
            */

                if ($isWajib) {
                    $totalWajib += $sks;
                } else {
                    $totalPilihan += $sks;
                }


                /*
            |--------------------------------------------------------------------------
            | Masukkan ke existing
            |--------------------------------------------------------------------------
            */

                $existingMataKuliah[] = $idMk;


                /*
            |--------------------------------------------------------------------------
            | Berhasil
            |--------------------------------------------------------------------------
            */

                $berhasil[] = [
                    'id_mata_kuliah' => $idMk,
                    'nama_mata_kuliah' => $mataKuliah->nama_mata_kuliah ?? null,
                    'sks' => $sks,
                    'jenis' => $statusMk,
                ];
            }


            /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

            DB::commit();


            /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

            $statusWajib =
                $totalWajib < $targetWajib
                ? 'kurang'
                : ($totalWajib == $targetWajib
                    ? 'terpenuhi'
                    : 'lebih');


            $statusPilihan =
                $totalPilihan < $targetPilihan
                ? 'kurang'
                : ($totalPilihan == $targetPilihan
                    ? 'terpenuhi'
                    : 'lebih');


            /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'success' => true,

                'message' => count($ditolak) > 0
                    ? 'Mata kuliah berhasil diproses. Beberapa mata kuliah tidak dapat ditambahkan karena melebihi target SKS.'
                    : 'Mata kuliah berhasil ditambahkan.',

                'data' => [

                    'target' => [
                        'wajib' => $targetWajib,
                        'pilihan' => $targetPilihan,
                        'lulus' => $targetWajib + $targetPilihan,
                    ],

                    'total' => [
                        'wajib' => $totalWajib,
                        'pilihan' => $totalPilihan,
                        'lulus' => $totalWajib + $totalPilihan,
                    ],

                    'kekurangan' => [
                        'wajib' => max(
                            0,
                            $targetWajib - $totalWajib
                        ),

                        'pilihan' => max(
                            0,
                            $targetPilihan - $totalPilihan
                        ),
                    ],

                    'status' => [
                        'wajib' => $statusWajib,
                        'pilihan' => $statusPilihan,

                        'kurikulum' =>
                        $statusWajib === 'terpenuhi'
                            && $statusPilihan === 'terpenuhi'
                            ? 'lengkap'
                            : 'belum_lengkap',
                    ],

                    'berhasil' => $berhasil,

                    'ditolak' => $ditolak,

                    'duplikat' => $duplikat,

                    'kurikulum' => $kurikulum
                        ->fresh()
                        ->load('mataKuliah'),
                ],
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

                // SEMENTARA untuk debugging
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
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
            'id_semester' => $item->id_semester,
            'nama_struktur_mk' => $item->nama_struktur_mk,
            'nama_kurikulum' => $item->nama_kurikulum,
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
}
