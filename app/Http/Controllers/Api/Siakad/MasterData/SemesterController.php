<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Semester::orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $data = Semester::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan',
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
            'semester' => 'required|string|max:30|unique:semester,semester',
            'tahun_ajaran' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date',
            'status' => 'required|in:Aktif,Selesai,Akan Datang',
        ]);

        $data = Semester::create([
            'semester' => $validated['semester'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Semester berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = Semester::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'semester' => 'sometimes|required|string|max:30|unique:semester,semester,' . $id,
            'tahun_ajaran' => 'sometimes|required|string|max:255',
            'tanggal_mulai' => 'sometimes|required|date',
            'tanggal_selesai' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:Aktif,Selesai,Akan Datang',
        ]);

        $data->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Semester berhasil diperbarui',
            'data' => $data->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = Semester::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semester berhasil dihapus',
        ]);
    }
}
