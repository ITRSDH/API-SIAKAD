<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\KurikulumInduk;
use App\Models\MasterData\RefJenisKurikulum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RefJenisKurikulumController extends Controller
{
    public function index(): JsonResponse
    {
        $data = RefJenisKurikulum::query()
            ->orderByDesc('is_aktif')
            ->orderBy('kode_jenis')
            ->get()
            ->map(fn(RefJenisKurikulum $item) => [
                'id' => $item->id,
                'kode_jenis' => $item->kode_jenis,
                'nama_jenis_kurikulum' => $item->nama_jenis_kurikulum,
                'is_aktif' => $item->is_aktif,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Data referensi jenis kurikulum berhasil diambil',
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'ID harus berupa UUID yang valid',
            ], 400);
        }

        $item = RefJenisKurikulum::query()->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Referensi jenis kurikulum tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail referensi jenis kurikulum berhasil diambil',
            'data' => [
                'id' => $item->id,
                'kode_jenis' => $item->kode_jenis,
                'nama_jenis_kurikulum' => $item->nama_jenis_kurikulum,
                'is_aktif' => $item->is_aktif,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_jenis' => 'required|string|max:20|unique:ref_jenis_kurikulum,kode_jenis',
            'nama_jenis_kurikulum' => 'required|string|max:150',
            'is_aktif' => 'nullable|boolean',
        ]);

        if (!array_key_exists('is_aktif', $validated)) {
            $validated['is_aktif'] = true;
        }

        $item = RefJenisKurikulum::create([
            'kode_jenis' => strtoupper(trim($validated['kode_jenis'])),
            'nama_jenis_kurikulum' => trim($validated['nama_jenis_kurikulum']),
            'is_aktif' => $validated['is_aktif'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Referensi jenis kurikulum berhasil ditambahkan',
            'data' => [
                'id' => $item->id,
                'kode_jenis' => $item->kode_jenis,
                'nama_jenis_kurikulum' => $item->nama_jenis_kurikulum,
                'is_aktif' => $item->is_aktif,
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'ID harus berupa UUID yang valid',
            ], 400);
        }

        $item = RefJenisKurikulum::query()->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Referensi jenis kurikulum tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'kode_jenis' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('ref_jenis_kurikulum', 'kode_jenis')->ignore($item->id),
            ],
            'nama_jenis_kurikulum' => 'sometimes|required|string|max:150',
            'is_aktif' => 'sometimes|boolean',
        ]);

        if (array_key_exists('kode_jenis', $validated)) {
            $validated['kode_jenis'] = strtoupper(trim($validated['kode_jenis']));
        }

        if (array_key_exists('nama_jenis_kurikulum', $validated)) {
            $validated['nama_jenis_kurikulum'] = trim($validated['nama_jenis_kurikulum']);
        }

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Referensi jenis kurikulum berhasil diperbarui',
            'data' => [
                'id' => $item->id,
                'kode_jenis' => $item->kode_jenis,
                'nama_jenis_kurikulum' => $item->nama_jenis_kurikulum,
                'is_aktif' => $item->is_aktif,
            ],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'ID harus berupa UUID yang valid',
            ], 400);
        }

        $item = RefJenisKurikulum::query()->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Referensi jenis kurikulum tidak ditemukan',
            ], 404);
        }

        $isUsed = KurikulumInduk::query()
            ->where('id_jenis_kurikulum', $item->id)
            ->exists();

        if ($isUsed) {
            return response()->json([
                'success' => false,
                'message' => 'Referensi jenis kurikulum tidak dapat dihapus karena masih digunakan oleh kurikulum induk.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Referensi jenis kurikulum berhasil dihapus',
        ]);
    }
}
