<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Cpl;
use App\Models\MasterData\IndikatorKinerja;
use App\Models\MasterData\MataKuliah;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PemetaanCPLMKController extends Controller
{
    /**
     * 📥 GET: Ambil data CPL & MK + mapping
     * level_pemetaan: cpl (CPL saja) atau cpl_ik (CPL + Indikator Kinerja)
     */
    public function index(Request $request, string $id_prodi): JsonResponse
    {
        try {
            $levelPemetaan = $request->get('level_pemetaan', 'cpl');

            // ✅ Validasi level
            if (!in_array($levelPemetaan, ['cpl', 'cpl_ik'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Level pemetaan tidak valid. Pilih: cpl atau cpl_ik'
                ], 400);
            }

            // ✅ Load data (tanpa relasi aneh-aneh)
            $cpls = Cpl::with(['mataKuliah', 'indikatorKinerja'])
                ->where('id_prodi', $id_prodi)
                ->get();

            $mataKuliahs = MataKuliah::where('id_prodi', $id_prodi)->get();

            $mapping = [];
            $indikatorKinerjaMapping = [];

            // ✅ Build mapping
            foreach ($cpls as $cpl) {
                foreach ($mataKuliahs as $mk) {
                    // Ambil pivot CPL ↔ MK
                    $pivot = $cpl->mataKuliah->firstWhere('id', $mk->id);
                    $bobot = $pivot?->pivot->bobot ?? 0;

                    // Simpan mapping CPL
                    $mapping[$cpl->id][$mk->id] = $bobot;

                    // ✅ Kalau mode CPL + IK → IK ikut bobot CPL
                    if ($levelPemetaan === 'cpl_ik') {
                        foreach ($cpl->indikatorKinerja as $ik) {
                            $indikatorKinerjaMapping[$ik->id][$mk->id] = $bobot;
                        }
                    }
                }
            }

            // ✅ Response base
            $response = [
                'success' => true,
                'message' => 'Data mapping CPL-MK berhasil diambil',
                'data' => [
                    'level_pemetaan' => $levelPemetaan,
                    'cpl' => $cpls,
                    'mata_kuliah' => $mataKuliahs,
                    'mapping' => $mapping
                ]
            ];

            // ✅ Tambahkan IK kalau diperlukan
            if ($levelPemetaan === 'cpl_ik') {
                $response['data']['indikator_kinerja_mapping'] = $indikatorKinerjaMapping;
            }

            return response()->json($response, 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error' => $e->getMessage() // opsional, bisa dihapus di production
            ], 500);
        }
    }

    /**
     * 💾 POST: Simpan mapping CPL → MK (mode otomatis)
     * Support: level_pemetaan untuk menentukan mode penyimpanan
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'level_pemetaan' => 'required|in:cpl,cpl_ik',
            'mapping' => 'required|array',
            'mapping.*' => 'required|array',
            'mapping.*.*' => 'nullable|numeric|min:0|max:100',
            'indikator_kinerja_mapping' => 'nullable|array',
            'indikator_kinerja_mapping.*' => 'nullable|array',
            'indikator_kinerja_mapping.*.*' => 'nullable|numeric|min:0|max:100'
        ]);

        DB::beginTransaction();

        try {
            $levelPemetaan = $request->level_pemetaan;

            // Simpan mapping CPL ke MK
            foreach ($request->mapping as $cplId => $mkData) {
                $cpl = Cpl::findOrFail($cplId);
                $syncData = [];

                $selected = array_filter($mkData, fn($v) => $v > 0);
                $count = count($selected);

                if ($count > 0) {
                    $bobot = round(100 / $count, 2);
                    $sisa = 100;

                    $i = 0;
                    foreach ($selected as $mkId => $val) {
                        $nilai = ($i == $count - 1) ? $sisa : $bobot;
                        $syncData[$mkId] = ['bobot' => $nilai];
                        $sisa -= $nilai;
                        $i++;
                    }
                }

                $cpl->mataKuliah()->sync($syncData);
            }

            // Simpan mapping Indikator Kinerja ke MK (jika mode cpl_ik)
            if ($levelPemetaan === 'cpl_ik' && $request->has('indikator_kinerja_mapping')) {
                foreach ($request->indikator_kinerja_mapping as $ikId => $mkData) {
                    $ik = IndikatorKinerja::findOrFail($ikId);
                    $syncData = [];

                    $selected = array_filter($mkData, fn($v) => $v > 0);
                    $count = count($selected);

                    if ($count > 0) {
                        $bobot = round(100 / $count, 2);
                        $sisa = 100;

                        $i = 0;
                        foreach ($selected as $mkId => $val) {
                            $nilai = ($i == $count - 1) ? $sisa : $bobot;
                            $syncData[$mkId] = ['bobot' => $nilai];
                            $sisa -= $nilai;
                            $i++;
                        }
                    }

                    $ik->mataKuliah()->sync($syncData);
                }
            }

            DB::commit();

            $message = $levelPemetaan === 'cpl_ik'
                ? 'Mapping CPL → MK dan Indikator Kinerja → MK berhasil disimpan'
                : 'Mapping CPL → MK berhasil disimpan';

            return response()->json([
                'success' => true,
                'message' => $message,
                'level_pemetaan' => $levelPemetaan
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
