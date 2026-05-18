<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\KonversiMataKuliah;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumMataKuliah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KonversiMataKuliahController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KonversiMataKuliah::with([
            'kurikulumAsal:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'kurikulumTujuan:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'mataKuliahAsal:id,kode_mk,nama_mk,sks',
            'mataKuliahTujuan:id,kode_mk,nama_mk,sks',
            'createdBy:id,name',
        ])->orderByDesc('created_at');

        if ($request->filled('id_kurikulum_asal')) {
            $query->where('id_kurikulum_asal', $request->id_kurikulum_asal);
        }

        if ($request->filled('id_kurikulum_tujuan')) {
            $query->where('id_kurikulum_tujuan', $request->id_kurikulum_tujuan);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $rule = KonversiMataKuliah::with([
            'kurikulumAsal:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'kurikulumTujuan:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'mataKuliahAsal:id,kode_mk,nama_mk,sks',
            'mataKuliahTujuan:id,kode_mk,nama_mk,sks',
            'createdBy:id,name',
        ])->find($id);

        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Aturan konversi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rule,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->assertValidReferences($validated);

        $rule = KonversiMataKuliah::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi mata kuliah berhasil dibuat.',
            'data' => $rule->load($this->relations()),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rule = KonversiMataKuliah::find($id);
        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Aturan konversi tidak ditemukan.',
            ], 404);
        }

        $validated = $this->validatePayload($request, $rule->id);
        $this->assertValidReferences($validated, $rule->id);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi mata kuliah berhasil diperbarui.',
            'data' => $rule->fresh($this->relations()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $rule = KonversiMataKuliah::find($id);
        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Aturan konversi tidak ditemukan.',
            ], 404);
        }

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aturan konversi mata kuliah berhasil dihapus.',
        ]);
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'id_kurikulum_asal' => 'required|uuid|exists:kurikulum,id',
            'id_kurikulum_tujuan' => 'required|uuid|different:id_kurikulum_asal|exists:kurikulum,id',
            'id_mata_kuliah_asal' => 'required|uuid|exists:mata_kuliah,id',
            'id_mata_kuliah_tujuan' => 'required|uuid|exists:mata_kuliah,id',
            'status_konversi' => ['required', Rule::in([
                KonversiMataKuliah::STATUS_DIAKUI,
                KonversiMataKuliah::STATUS_WAJIB_ULANG,
                KonversiMataKuliah::STATUS_PILIHAN_BEBAS,
            ])],
            'min_bobot_nilai' => 'nullable|numeric|min:0|max:4',
            'catatan' => 'nullable|string',
        ]);
    }

    private function assertValidReferences(array $validated, ?string $ignoreId = null): void
    {
        $kurikulumAsal = Kurikulum::findOrFail($validated['id_kurikulum_asal']);
        $kurikulumTujuan = Kurikulum::findOrFail($validated['id_kurikulum_tujuan']);

        if ($kurikulumAsal->id_prodi !== $kurikulumTujuan->id_prodi) {
            throw ValidationException::withMessages([
                'id_kurikulum_tujuan' => ['Kurikulum asal dan tujuan harus berada pada program studi yang sama.'],
            ]);
        }

        $isMataKuliahAsalValid = KurikulumMataKuliah::query()
            ->where('id_kurikulum', $validated['id_kurikulum_asal'])
            ->where('id_mata_kuliah', $validated['id_mata_kuliah_asal'])
            ->exists();

        if (!$isMataKuliahAsalValid) {
            throw ValidationException::withMessages([
                'id_mata_kuliah_asal' => ['Mata kuliah asal tidak terdaftar pada kurikulum asal.'],
            ]);
        }

        $isMataKuliahTujuanValid = KurikulumMataKuliah::query()
            ->where('id_kurikulum', $validated['id_kurikulum_tujuan'])
            ->where('id_mata_kuliah', $validated['id_mata_kuliah_tujuan'])
            ->exists();

        if (!$isMataKuliahTujuanValid) {
            throw ValidationException::withMessages([
                'id_mata_kuliah_tujuan' => ['Mata kuliah tujuan tidak terdaftar pada kurikulum tujuan.'],
            ]);
        }

        $duplicateQuery = KonversiMataKuliah::query()
            ->where('id_kurikulum_asal', $validated['id_kurikulum_asal'])
            ->where('id_kurikulum_tujuan', $validated['id_kurikulum_tujuan'])
            ->where('id_mata_kuliah_asal', $validated['id_mata_kuliah_asal'])
            ->where('id_mata_kuliah_tujuan', $validated['id_mata_kuliah_tujuan']);

        if ($ignoreId) {
            $duplicateQuery->where('id', '!=', $ignoreId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'id_mata_kuliah_tujuan' => ['Aturan konversi yang sama sudah terdaftar.'],
            ]);
        }
    }

    private function relations(): array
    {
        return [
            'kurikulumAsal:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'kurikulumTujuan:id,id_prodi,kode_kurikulum,nama_kurikulum',
            'mataKuliahAsal:id,kode_mk,nama_mk,sks',
            'mataKuliahTujuan:id,kode_mk,nama_mk,sks',
            'createdBy:id,name',
        ];
    }
}
