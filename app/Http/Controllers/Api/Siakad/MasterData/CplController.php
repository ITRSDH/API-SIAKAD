<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Cpl;
use App\Models\MasterData\IndikatorKinerja;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CplController extends Controller
{
    public function index(Request $request, $id_prodi): JsonResponse
    {
        try {
            $draw   = intval($request->input('draw', 1));
            $start  = intval($request->input('start', 0));
            $length = intval($request->input('length', 10));
            $search = $request->input('search.value');

            $baseQuery = Cpl::where('id_prodi', $id_prodi);
            $recordsTotal = $baseQuery->count();

            $query = clone $baseQuery;

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_cpl', 'like', "%{$search}%")
                        ->orWhere('cpl', 'like', "%{$search}%")
                        ->orWhere('deskripsi_cpl_indonesia', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = $query->count();

            $data = $query
                ->with(['indikatorKinerja' => function ($ikQuery) {
                    $ikQuery->select('id', 'id_cpl', 'kode_ik_cpl', 'deskripsi_ik_cpl_indonesia', 'kategori_ik_cpl');
                }])
                ->orderBy('kode_cpl', 'asc')
                ->skip($start)
                ->take($length > 0 ? $length : 10)
                ->get([
                    'id',
                    'kode_cpl',
                    'cpl',
                    'deskripsi_cpl_indonesia',
                    'deskripsi_cpl_english',
                    'kategori_cpl'
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

    public function store(Request $request, $id_prodi): JsonResponse
    {
        try {
            $prodi = Prodi::findOrFail($id_prodi);

            $validatedData = $request->validate([
                'kode_cpl' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('cpl')->where('id_prodi', $id_prodi)
                ],
                'cpl' => 'required|string|max:255',
                'deskripsi_cpl_indonesia' => 'required|string',
                'deskripsi_cpl_english' => 'nullable|string',
                'kategori_cpl' => 'required|in:S,P,KU,KK',
                'indikator_kinerja' => 'nullable|array',
                'indikator_kinerja.*.kode_ik_cpl' => 'required|string|max:20',
                'indikator_kinerja.*.deskripsi_ik_cpl_indonesia' => 'required|string',
                'indikator_kinerja.*.deskripsi_ik_cpl_english' => 'nullable|string',
                'indikator_kinerja.*.kategori_ik_cpl' => 'required|in:S,P,KU,KK'
            ]);

            $validatedData['id_prodi'] = $id_prodi;

            DB::beginTransaction();

            $cpl = Cpl::create($validatedData);

            if (!empty($validatedData['indikator_kinerja'])) {
                foreach ($validatedData['indikator_kinerja'] as $ikData) {
                    $ikData['id_cpl'] = $cpl->id;
                    IndikatorKinerja::create($ikData);
                }
            }

            DB::commit();

            $cpl->load('indikatorKinerja');

            return response()->json([
                'status' => 'success',
                'data' => $cpl,
            ], 201);
        } catch (ValidationException $ve) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $cpl = Cpl::with(['indikatorKinerja' => function ($ikQuery) {
                $ikQuery->select('id', 'id_cpl', 'kode_ik_cpl', 'deskripsi_ik_cpl_indonesia', 'deskripsi_ik_cpl_english', 'kategori_ik_cpl');
            }])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $cpl,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id, $id_prodi): JsonResponse
    {
        try {
            $prodi = Prodi::findOrFail($id_prodi);
            $cpl = Cpl::findOrFail($id);

            $validatedData = $request->validate([
                'kode_cpl' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('cpl')->where('id_prodi', $id_prodi)->ignore($id)
                ],
                'cpl' => 'required|string|max:255',
                'deskripsi_cpl_indonesia' => 'required|string',
                'deskripsi_cpl_english' => 'nullable|string',
                'kategori_cpl' => 'required|in:S,P,KU,KK',
                'indikator_kinerja' => 'nullable|array',
                'indikator_kinerja.*.id' => 'nullable|string',
                'indikator_kinerja.*.kode_ik_cpl' => 'required|string|max:20',
                'indikator_kinerja.*.deskripsi_ik_cpl_indonesia' => 'required|string',
                'indikator_kinerja.*.deskripsi_ik_cpl_english' => 'nullable|string',
                'indikator_kinerja.*.kategori_ik_cpl' => 'required|in:S,P,KU,KK'
            ]);

            $validatedData['id_prodi'] = $id_prodi;

            DB::beginTransaction();

            $cpl->update($validatedData);

            if (isset($validatedData['indikator_kinerja'])) {
                $existingIkIds = $cpl->indikatorKinerja()->pluck('id')->toArray();
                $newIkIds = [];

                foreach ($validatedData['indikator_kinerja'] as $ikData) {
                    $ikData['id_cpl'] = $cpl->id;

                    if (isset($ikData['id'])) {
                        $ik = IndikatorKinerja::findOrFail($ikData['id']);
                        if ($ik->id_cpl === $cpl->id) {
                            $ik->update($ikData);
                            $newIkIds[] = $ik->id;
                        }
                    } else {
                        $newIk = IndikatorKinerja::create($ikData);
                        $newIkIds[] = $newIk->id;
                    }
                }

                $ikToDelete = array_diff($existingIkIds, $newIkIds);
                if (!empty($ikToDelete)) {
                    IndikatorKinerja::whereIn('id', $ikToDelete)->delete();
                }
            }

            DB::commit();

            $cpl->load('indikatorKinerja');

            return response()->json([
                'status' => 'success',
                'data' => $cpl,
            ], 200);
        } catch (ValidationException $ve) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $cpl = Cpl::findOrFail($id);

            DB::beginTransaction();

            $cpl->indikatorKinerja()->delete();
            $cpl->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'CPL dan Indikator Kinerja berhasil dihapus.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addIndikatorKinerja(Request $request, string $id): JsonResponse
    {
        try {
            $cpl = Cpl::findOrFail($id);

            $validatedData = $request->validate([
                'indikator_kinerja' => 'required|array',
                'indikator_kinerja.*.kode_ik_cpl' => 'required|string|max:20',
                'indikator_kinerja.*.deskripsi_ik_cpl_indonesia' => 'required|string',
                'indikator_kinerja.*.deskripsi_ik_cpl_english' => 'nullable|string',
                'indikator_kinerja.*.kategori_ik_cpl' => 'required|in:S,P,KU,KK'
            ]);

            DB::beginTransaction();

            foreach ($validatedData['indikator_kinerja'] as $ikData) {
                $ikData['id_cpl'] = $cpl->id;
                IndikatorKinerja::create($ikData);
            }

            DB::commit();

            $cpl->load('indikatorKinerja');

            return response()->json([
                'status' => 'success',
                'data' => $cpl,
                'message' => 'Indikator Kinerja berhasil ditambahkan.',
            ], 201);
        } catch (ValidationException $ve) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateIndikatorKinerja(Request $request, string $id, string $ik_id): JsonResponse
    {
        try {
            $cpl = Cpl::findOrFail($id);
            $indikatorKinerja = IndikatorKinerja::where('id', $ik_id)
                ->where('id_cpl', $id)
                ->firstOrFail();

            $validatedData = $request->validate([
                'kode_ik_cpl' => 'required|string|max:20',
                'deskripsi_ik_cpl_indonesia' => 'required|string',
                'deskripsi_ik_cpl_english' => 'nullable|string',
                'kategori_ik_cpl' => 'required|in:S,P,KU,KK'
            ]);

            $indikatorKinerja->update($validatedData);

            return response()->json([
                'status' => 'success',
                'data' => $indikatorKinerja,
                'message' => 'Indikator Kinerja berhasil diperbarui.',
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

    public function deleteIndikatorKinerja(string $id, string $ik_id): JsonResponse
    {
        try {
            $cpl = Cpl::findOrFail($id);
            $indikatorKinerja = IndikatorKinerja::where('id', $ik_id)
                ->where('id_cpl', $id)
                ->firstOrFail();

            $indikatorKinerja->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Indikator Kinerja berhasil dihapus.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
