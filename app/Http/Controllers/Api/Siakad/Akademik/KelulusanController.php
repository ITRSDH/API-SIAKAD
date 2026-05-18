<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Kelulusan;
use App\Models\Akademik\Yudisium;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KelulusanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Kelulusan::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'yudisium:id,id_mahasiswa,status,predikat_lulus,tanggal_yudisium',
        ])->orderByDesc('generated_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $kelulusan = Kelulusan::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'yudisium.transkrip:id,id_mahasiswa,total_sks_lulus,ipk',
        ])->find($id);

        if (!$kelulusan) {
            return response()->json([
                'success' => false,
                'message' => 'Kelulusan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kelulusan,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'tanggal_lulus' => 'nullable|date',
            'nomor_sk' => 'nullable|string|max:100',
            'nomor_ijazah' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,ditetapkan',
            'catatan' => 'nullable|string',
        ]);

        $yudisium = Yudisium::where('id_mahasiswa', $validated['id_mahasiswa'])->first();
        if (!$yudisium) {
            return response()->json([
                'success' => false,
                'message' => 'Yudisium belum tersedia',
            ], 404);
        }

        if ($yudisium->status !== 'memenuhi') {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa belum memenuhi syarat yudisium',
            ], 422);
        }

        $kelulusan = Kelulusan::updateOrCreate(
            [
                'id_mahasiswa' => $validated['id_mahasiswa'],
            ],
            [
                'id_yudisium' => $yudisium->id,
                'tanggal_lulus' => $validated['tanggal_lulus'] ?? now()->toDateString(),
                'nomor_sk' => $validated['nomor_sk'] ?? null,
                'nomor_ijazah' => $validated['nomor_ijazah'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'catatan' => $validated['catatan'] ?? null,
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Data kelulusan berhasil digenerate',
            'data' => $kelulusan->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'yudisium:id,id_mahasiswa,status,predikat_lulus,tanggal_yudisium',
            ]),
        ]);
    }
}
