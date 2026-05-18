<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\RuangKuliah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuangKuliahController extends Controller
{
    public function index(): JsonResponse
    {
        $data = RuangKuliah::orderBy('gedung')
            ->orderBy('nama_ruang')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $data = RuangKuliah::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Ruang kuliah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_ruang' => 'required|string|max:30|unique:ruang_kuliah,kode_ruang',
            'nama_ruang' => 'required|string|max:255',
            'gedung' => 'nullable|string|max:255',
            'lantai' => 'nullable|string|max:20',
            'kapasitas' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data = RuangKuliah::create([
            'kode_ruang' => $validated['kode_ruang'],
            'nama_ruang' => $validated['nama_ruang'],
            'gedung' => $validated['gedung'] ?? null,
            'lantai' => $validated['lantai'] ?? null,
            'kapasitas' => $validated['kapasitas'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ruang kuliah berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = RuangKuliah::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Ruang kuliah tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'kode_ruang' => 'sometimes|required|string|max:30|unique:ruang_kuliah,kode_ruang,' . $id,
            'nama_ruang' => 'sometimes|required|string|max:255',
            'gedung' => 'nullable|string|max:255',
            'lantai' => 'nullable|string|max:20',
            'kapasitas' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ruang kuliah berhasil diperbarui',
            'data' => $data->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = RuangKuliah::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Ruang kuliah tidak ditemukan',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ruang kuliah berhasil dihapus',
        ]);
    }
}
