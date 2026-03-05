<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\MataKuliah;
use Illuminate\Validation\ValidationException;

class KurikulumController extends Controller
{
    public function index(): JsonResponse
    {
        try {

            $kurikulum = Kurikulum::with([
                'prodi:id,nama_prodi,jenjang_pendidikan',
                'semesterMulai.tahunAkademik:id,tahun_akademik',
                'mataKuliah:id,sks'
            ])
                ->get()
                ->map(function ($item) {

                    // 🔥 Hitung realisasi SKS dari relasi
                    $totalWajib = $item->mataKuliah
                        ->where('pivot.is_wajib', 1)
                        ->sum('sks');

                    $totalPilihan = $item->mataKuliah
                        ->where('pivot.is_wajib', 0)
                        ->sum('sks');

                    return [
                        'id' => $item->id,
                        'nama_kurikulum' => $item->nama_kurikulum,

                        // ✅ ATURAN
                        'jumlah_sks_lulus' => $item->jumlah_sks_lulus,
                        'jumlah_sks_wajib' => $item->jumlah_sks_wajib,
                        'jumlah_sks_pilihan' => $item->jumlah_sks_pilihan,

                        // ✅ REALISASI DARI MATA KULIAH
                        'jumlah_sks_wajib_mk' => $totalWajib,
                        'jumlah_sks_pilihan_mk' => $totalPilihan,

                        'prodi' => $item->prodi
                            ? "({$item->prodi->jenjang_pendidikan}) {$item->prodi->nama_prodi}"
                            : '-',

                        'semester_mulai' => $item->semesterMulai
                            ? $item->semesterMulai->tahunAkademik->tahun_akademik
                            . ' ' .
                            $item->semesterMulai->nama_semester
                            : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data All Kurikulum berhasil diambil',
                'data' => $kurikulum,
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data All Kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function matakuliahByProdi(string $id_kurikulum): JsonResponse
    {
        try {
            // Ambil kurikulum dan prodi
            $kurikulum = Kurikulum::findOrFail($id_kurikulum);
            $idProdi = $kurikulum->id_prodi;

            $mataKuliah = MataKuliah::select('id', 'kode_mk', 'nama_mk', 'sks', 'sks_tatap_muka', 'sks_praktikum', 'sks_praktek_lapangan', 'sks_simulasi')
                ->where('id_prodi', $idProdi) // Ambil MK milik prodi kurikulum
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
            // Ambil kurikulum dan prodi
            $kurikulum = Kurikulum::findOrFail($id_kurikulum);
            $idProdi = $kurikulum->id_prodi;

            $kurikulumList = Kurikulum::select('id', 'nama_kurikulum')
                ->where('id_prodi', $idProdi) // Ambil kurikulum milik prodi yang sama
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kurikulum berhasil diambil',
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
                'nama_kurikulum',
                'id_semester',
                'jumlah_sks_lulus',
                'jumlah_sks_wajib',
                'jumlah_sks_pilihan',
            ])
                ->with([
                    'prodi:id,jenjang_pendidikan,nama_prodi',
                    'semesterMulai:id,id_tahun_akademik,nama_semester',
                    'semesterMulai.tahunAkademik:id,tahun_akademik',
                ])
                ->findOrFail($id);

            // 🔥 Ambil data mata kuliah dari pivot secara manual
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

            // 🔥 Format ulang supaya clean
            $data = [
                'id' => $kurikulum->id,
                'id_prodi' => $kurikulum->id_prodi,
                'id_semester' => $kurikulum->id_semester,
                'nama_kurikulum' => $kurikulum->nama_kurikulum,
                'jumlah_sks_lulus' => $kurikulum->jumlah_sks_lulus,
                'jumlah_sks_wajib' => $kurikulum->jumlah_sks_wajib,
                'jumlah_sks_pilihan' => $kurikulum->jumlah_sks_pilihan,

                'prodi' => $kurikulum->prodi
                    ? "({$kurikulum->prodi->jenjang_pendidikan}) {$kurikulum->prodi->nama_prodi}"
                    : null,

                'semester_mulai' => $kurikulum->semesterMulai
                    ? $kurikulum->semesterMulai->tahunAkademik->tahun_akademik
                    . ' ' .
                    $kurikulum->semesterMulai->nama_semester
                    : null,

                'mata_kuliah' => $mataKuliahDiKurikulum,
            ];

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
            $validatedData = $request->validate([
                'id_prodi' => 'nullable|exists:prodi,id',
                'nama_kurikulum' => [
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

            // Hitung total SKS berdasarkan penjumlahan semua jenis SKS
            $totalSks = ($validatedData['jumlah_sks_wajib'] ?? 0) +
                ($validatedData['jumlah_sks_pilihan'] ?? 0);
            $validatedData['jumlah_sks_lulus'] = $totalSks;

            $kurikulum = Kurikulum::create($validatedData);
            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil ditambahkan',
                'data' => $kurikulum
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

            $kurikulum = Kurikulum::findOrFail($id);

            $validatedData = $request->validate([
                'id_prodi' => 'nullable|exists:prodi,id',

                'nama_kurikulum' => [
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

            // Hitung total SKS berdasarkan penjumlahan semua jenis SKS
            $totalSks = ($validatedData['jumlah_sks_wajib'] ?? 0) +
                ($validatedData['jumlah_sks_pilihan'] ?? 0);
            $validatedData['jumlah_sks_lulus'] = $totalSks;

            $kurikulum->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diperbarui',
                'data' => $kurikulum
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
                'message' => 'Kurikulum dan data mata kuliah terkait berhasil dihapus',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menambahkan mata kuliah ke kurikulum secara manual.
     */
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

                $isWajibBool = isset($mk['is_wajib']) ? (bool)$mk['is_wajib'] : false;
                $statusMk = $isWajibBool ? 'wajib' : 'pilihan';

                // Cegah duplicate
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

            // ✅ VALIDASI TOTAL SKS (WAJIB & PILIHAN DIPISAH)
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
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan mata kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memperbarui data mata kuliah di kurikulum (pivot).
     */
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

            // Filter hanya field yang tidak null
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

    /**
     * Menghapus mata kuliah dari kurikulum.
     */
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
            // 🔥 Ganti dengan query manual tanpa with()
            $kurikulumTujuan = Kurikulum::findOrFail($id_kurikulum_tujuan);

            // Ambil data mata kuliah dari kurikulum asal secara manual
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
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'id_kurikulum' => $kurikulumTujuan->id,
                    'id_mata_kuliah' => $mk->id_mata_kuliah,
                    'semester_ke' => $mk->semester_ke,
                    'status_mk' => $statusMk,
                    'is_wajib' => $isWajibBool,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Cek apakah sudah ada, jika belum maka insert
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
}
