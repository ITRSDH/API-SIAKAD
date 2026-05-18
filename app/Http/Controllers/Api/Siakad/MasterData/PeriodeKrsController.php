<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\PeriodeKrs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeKrsController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PeriodeKrs::with('semester.tahunAkademik')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $data = PeriodeKrs::with('semester.tahunAkademik')->find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Periode KRS tidak ditemukan',
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
            'id_semester' => 'required|uuid|exists:semester,id|unique:periode_krs,id_semester',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status' => 'nullable|in:draft,aktif,ditutup',
            'catatan' => 'nullable|string',
        ]);

        if (($validated['status'] ?? null) === 'aktif') {
            PeriodeKrs::where('status', 'aktif')->update(['status' => 'ditutup']);
        }

        $data = PeriodeKrs::create([
            'id_semester' => $validated['id_semester'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'status' => $validated['status'] ?? 'draft',
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Periode KRS berhasil ditambahkan',
            'data' => $data->load('semester.tahunAkademik'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = PeriodeKrs::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Periode KRS tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'id_semester' => 'sometimes|required|uuid|exists:semester,id|unique:periode_krs,id_semester,' . $id,
            'tanggal_mulai' => 'sometimes|required|date',
            'tanggal_selesai' => 'sometimes|required|date|after_or_equal:tanggal_mulai',
            'status' => 'nullable|in:draft,aktif,ditutup',
            'catatan' => 'nullable|string',
        ]);

        if (($validated['status'] ?? null) === 'aktif') {
            PeriodeKrs::where('status', 'aktif')
                ->where('id', '!=', $data->id)
                ->update(['status' => 'ditutup']);
        }

        $data->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode KRS berhasil diperbarui',
            'data' => $data->fresh()->load('semester.tahunAkademik'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = PeriodeKrs::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Periode KRS tidak ditemukan',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Periode KRS berhasil dihapus',
        ]);
    }
}
