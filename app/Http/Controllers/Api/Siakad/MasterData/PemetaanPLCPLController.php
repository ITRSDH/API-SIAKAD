<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Cpl;
use App\Models\MasterData\ProfileLulusan;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PemetaanPLCPLController extends Controller
{
    /**
     * 📥 GET: Ambil data PL & CPL + mapping
     */
    public function index(string $id_prodi): JsonResponse
    {
        try {
            $pls = ProfileLulusan::with('cpl')
                ->where('id_prodi', $id_prodi)
                ->get();

            $cpls = Cpl::where('id_prodi', $id_prodi)->get();

            // 🔁 Format agar mudah dipakai frontend (matrix)
            $mapping = [];

            foreach ($pls as $pl) {
                foreach ($cpls as $cpl) {
                    $pivot = $pl->cpl->firstWhere('id', $cpl->id);

                    $mapping[$pl->id][$cpl->id] = $pivot?->pivot->bobot ?? 0;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Data mapping PL-CPL berhasil diambil',
                'data' => [
                    'pl' => $pls,
                    'cpl' => $cpls,
                    'mapping' => $mapping
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 💾 POST: Simpan mapping PL → CPL
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'mapping' => 'required|array',
            'mapping.*' => 'required|array',
            'mapping.*.*' => 'nullable|numeric|min:0|max:100',
            'mode' => 'required|in:manual,otomatis'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->mapping as $plId => $cplData) {

                $pl = ProfileLulusan::findOrFail($plId);

                $syncData = [];

                if ($request->mode === 'manual') {

                    $total = round(array_sum($cplData), 2);

                    // 🔥 CASE 1: kalau semua 0 → hapus semua relasi
                    if ($total == 0.00) {
                        $pl->cpl()->sync([]);
                        continue;
                    }

                    // 🔥 CASE 2: harus 100%
                    if (round($total, 2) != 100) {
                        throw new Exception("Total bobot untuk PL {$plId} harus 100%");
                    }

                    foreach ($cplData as $cplId => $bobot) {
                        if ($bobot > 0) {
                            $syncData[$cplId] = ['bobot' => $bobot];
                        }
                    }
                }

                if ($request->mode === 'otomatis') {

                    $selected = array_filter($cplData, fn($v) => $v > 0);
                    $count = count($selected);

                    if ($count > 0) {
                        $bobot = round(100 / $count, 2);
                        $sisa = 100;

                        $i = 0;
                        foreach ($selected as $cplId => $val) {
                            $nilai = ($i == $count - 1) ? $sisa : $bobot;

                            $syncData[$cplId] = ['bobot' => $nilai];

                            $sisa -= $nilai;
                            $i++;
                        }
                    }
                }

                // 🔥 INI KUNCI store + update
                // kalau kosong → semua relasi dihapus
                $pl->cpl()->sync($syncData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mapping PL → CPL berhasil disimpan / diupdate'
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
