<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\MataKuliahPrasyarat;
use Illuminate\Validation\ValidationException;
use App\Imports\MataKuliahImport;
use App\Exports\MataKuliahExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class MataKuliahController extends Controller
{
    public function index(Request $request, $id_prodi): JsonResponse
    {
        try {

            // Parameter default DataTables
            $draw   = intval($request->input('draw', 1));
            $start  = intval($request->input('start', 0));
            $length = intval($request->input('length', 10));
            $search = $request->input('search.value');

            // Base query
            $baseQuery = MataKuliah::where('id_prodi', $id_prodi);

            // Total tanpa filter
            $recordsTotal = $baseQuery->count();

            // Clone query untuk filter
            $query = clone $baseQuery;

            // Searching
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_mk', 'like', "%{$search}%")
                        ->orWhere('nama_mk', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = $query->count();

            // Ambil data dengan pagination
            $data = $query
                ->orderBy('kode_mk', 'asc')
                ->skip($start)
                ->take($length > 0 ? $length : 10)
                ->get([
                    'id',
                    'kode_mk',
                    'nama_mk',
                    'sks',
                    'kelompok_mk',
                    'jenis_mk',
                ]);

            return response()->json([
                "draw" => $draw,
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $id_prodi): JsonResponse
    {
        try {
            // Validasi bahwa prodi ada
            $prodi = Prodi::findOrFail($id_prodi);

            $validatedData = $request->validate([
                'kode_mk' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('mata_kuliah')
                        ->where('id_prodi', $id_prodi)
                ],
                'nama_mk' => 'required|string|max:255',
                'sks_tatap_muka' => 'nullable|integer|min:0',
                'sks_praktikum' => 'nullable|integer|min:0',
                'sks_praktek_lapangan' => 'nullable|integer|min:0',
                'sks_simulasi' => 'nullable|integer|min:0',
                'jenis_mk' => 'nullable|in:wajib_prodi,wajib_nasional,pilihan,peminatan,tugas_akhir/skripsi/tesis/disertasi',
                'kelompok_mk' => 'nullable|in:MPK,MKK,MKB,MPB,MBB,MKDK',
            ]);

            // Hitung total SKS berdasarkan penjumlahan semua jenis SKS
            $totalSks = ($validatedData['sks_tatap_muka'] ?? 0) +
                ($validatedData['sks_praktikum'] ?? 0) +
                ($validatedData['sks_praktek_lapangan'] ?? 0) +
                ($validatedData['sks_simulasi'] ?? 0);

            // Tambahkan total SKS dan id_prodi otomatis ke dalam data yang akan disimpan
            $validatedData['sks'] = $totalSks;
            $validatedData['id_prodi'] = $id_prodi;

            $mataKuliah = MataKuliah::create($validatedData);

            return response()->json([
                'status' => 'success',
                'data' => $mataKuliah,
            ], 201);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::select([
                'id',
                'id_prodi',
                'kode_mk',
                'nama_mk',
                'sks_tatap_muka',
                'sks_praktikum',
                'sks_praktek_lapangan',
                'sks_simulasi',
                'sks',
                'jenis_mk',
                'kelompok_mk',
            ])->with([
                'prodi:id,kode_prodi,jenjang_pendidikan,nama_prodi,akreditasi,tahun_berdiri,gelar_lulusan',
                'prasyarat.mataKuliahPrasyarat:id,id_prodi,kode_mk,nama_mk'
            ])->findOrFail($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $mataKuliah,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, $id_prodi): JsonResponse
    {
        try {
            // Validasi bahwa prodi ada
            $prodi = Prodi::findOrFail($id_prodi);

            $mataKuliah = MataKuliah::findOrFail($id);

            if (!$mataKuliah) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mata Kuliah tidak ditemukan.',
                ], 404);
            }

            $validatedData = $request->validate([
                'kode_mk' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('mata_kuliah')->where('id_prodi', $id_prodi)->ignore($id)
                ],
                'nama_mk' => 'required|string|max:255',
                'sks_tatap_muka' => 'nullable|integer|min:0',
                'sks_praktikum' => 'nullable|integer|min:0',
                'sks_praktek_lapangan' => 'nullable|integer|min:0',
                'sks_simulasi' => 'nullable|integer|min:0',
                'jenis_mk' => 'nullable|in:wajib_prodi,wajib_nasional,pilihan,peminatan,tugas_akhir/skripsi/tesis/disertasi',
                'kelompok_mk' => 'nullable|in:MPK,MKK,MKB,MPB,MBB,MKDK',
            ]);

            // Hitung total SKS berdasarkan penjumlahan semua jenis SKS
            $totalSks = ($validatedData['sks_tatap_muka'] ?? 0) +
                ($validatedData['sks_praktikum'] ?? 0) +
                ($validatedData['sks_praktek_lapangan'] ?? 0) +
                ($validatedData['sks_simulasi'] ?? 0);

            // Tambahkan total SKS dan id_prodi otomatis ke dalam data yang akan diupdate
            $validatedData['sks'] = $totalSks;
            $validatedData['id_prodi'] = $id_prodi;

            // Update data mata kuliah
            $mataKuliah->update($validatedData);

            // Refresh data setelah update
            $mataKuliah->refresh();

            return response()->json([
                'status' => 'success',
                'data' => $mataKuliah,
            ], 200);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mk = MataKuliah::findOrFail($id);

            if (!$mk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            $mk->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Mata Kuliah deleted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import mata kuliah dari Excel
     */
    public function import(Request $request, $id_prodi): JsonResponse
    {
        try {
            // Validasi bahwa prodi ada
            $prodi = Prodi::findOrFail($id_prodi);

            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
            ]);

            $file = $request->file('file');

            // Import data
            $import = new MataKuliahImport($id_prodi);
            Excel::import($import, $file);

            return response()->json([
                'status' => 'success',
                'message' => 'Data mata kuliah berhasil diimport',
            ], 200);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada file import',
                'errors' => $e->failures(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export mata kuliah ke Excel
     */
    public function export(Request $request, $id_prodi)
    {
        try {
            // Validasi bahwa prodi ada
            $prodi = Prodi::findOrFail($id_prodi);

            $isDummy = $request->query('dummy', false);

            $filename = $isDummy
                ? 'format_import_mata_kuliah.xlsx'
                : 'data_mata_kuliah_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download(new MataKuliahExport($id_prodi, $isDummy), $filename);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download format import
     */
    public function downloadFormat($id_prodi)
    {
        return $this->export(request()->merge(['dummy' => true]), $id_prodi);
    }

    public function getPrasyarat(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::with([
                'prasyarat.mataKuliahPrasyarat:id,id_prodi,kode_mk,nama_mk,sks'
            ])->findOrFail($id);

            $data = $mataKuliah->prasyarat->map(function (MataKuliahPrasyarat $item) {
                return [
                    'id' => $item->id,
                    'id_mata_kuliah' => $item->id_mata_kuliah,
                    'id_mata_kuliah_prasyarat' => $item->id_mata_kuliah_prasyarat,
                    'min_bobot_nilai' => $item->min_bobot_nilai,
                    'mata_kuliah_prasyarat' => $item->mataKuliahPrasyarat,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'mata_kuliah' => [
                        'id' => $mataKuliah->id,
                        'kode_mk' => $mataKuliah->kode_mk,
                        'nama_mk' => $mataKuliah->nama_mk,
                    ],
                    'prasyarat' => $data,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function syncPrasyarat(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'prasyarat' => 'required|array',
            'prasyarat.*.id_mata_kuliah_prasyarat' => 'required|uuid|exists:mata_kuliah,id',
            'prasyarat.*.min_bobot_nilai' => 'nullable|numeric|min:0|max:4',
        ]);

        try {
            $mataKuliah = MataKuliah::findOrFail($id);

            DB::transaction(function () use ($validated, $mataKuliah) {
                MataKuliahPrasyarat::where('id_mata_kuliah', $mataKuliah->id)->delete();

                foreach ($validated['prasyarat'] as $item) {
                    if ($item['id_mata_kuliah_prasyarat'] === $mataKuliah->id) {
                        continue;
                    }

                    $mkPrasyarat = MataKuliah::find($item['id_mata_kuliah_prasyarat']);
                    if (!$mkPrasyarat || $mkPrasyarat->id_prodi !== $mataKuliah->id_prodi) {
                        continue;
                    }

                    MataKuliahPrasyarat::create([
                        'id_mata_kuliah' => $mataKuliah->id,
                        'id_mata_kuliah_prasyarat' => $item['id_mata_kuliah_prasyarat'],
                        'min_bobot_nilai' => $item['min_bobot_nilai'] ?? 2.00,
                    ]);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Prasyarat mata kuliah berhasil diperbarui',
            ]);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
