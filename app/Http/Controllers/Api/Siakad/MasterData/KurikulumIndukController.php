<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\KurikulumInduk;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\RefJenisKurikulum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KurikulumIndukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'id_prodi' => 'nullable|exists:prodi,id',
            'id_jenis_kurikulum' => 'nullable|exists:ref_jenis_kurikulum,id',
            'tahun_kurikulum' => 'nullable|digits:4',
            'is_aktif' => 'nullable|boolean',
        ]);

        $query = KurikulumInduk::with([
            'prodi:id,nama_prodi,jenjang_pendidikan,kode_prodi',
            'jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum,is_aktif',
            'kurikulumOperasional:id,id_kurikulum_induk,id_semester',
            'kurikulumOperasional.semesterMulai:id,id_tahun_akademik,nama_semester',
            'kurikulumOperasional.semesterMulai.tahunAkademik:id,tahun_akademik',
        ])
            ->withCount('kurikulumOperasional')
            ->orderByDesc('tahun_kurikulum')
            ->orderBy('kode_kurikulum');

        if ($request->filled('id_prodi')) {
            $query->where('id_prodi', $request->id_prodi);
        }

        if ($request->filled('id_jenis_kurikulum')) {
            $query->where('id_jenis_kurikulum', $request->id_jenis_kurikulum);
        }

        if ($request->filled('tahun_kurikulum')) {
            $query->where('tahun_kurikulum', $request->tahun_kurikulum);
        }

        if ($request->has('is_aktif')) {
            $query->where('is_aktif', filter_var($request->is_aktif, FILTER_VALIDATE_BOOLEAN));
        }

        $data = $query->get()->map(fn (KurikulumInduk $item) => $this->serializeKurikulumInduk($item));

        return response()->json([
            'success' => true,
            'message' => 'Data tahun kurikulum berhasil diambil',
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = KurikulumInduk::with([
            'prodi:id,nama_prodi,jenjang_pendidikan,kode_prodi',
            'jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum,is_aktif',
            'kurikulumOperasional:id,id_prodi,id_kurikulum_induk,nama_struktur_mk,id_semester',
            'kurikulumOperasional.semesterMulai:id,id_tahun_akademik,nama_semester',
            'kurikulumOperasional.semesterMulai.tahunAkademik:id,tahun_akademik',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail tahun kurikulum berhasil diambil',
            'data' => array_merge(
                $this->serializeKurikulumInduk($item),
                [
                    'struktur_operasional' => $item->kurikulumOperasional->map(function ($operasional) {
                        return [
                            'id' => $operasional->id,
                            'id_kurikulum_induk' => $operasional->id_kurikulum_induk,
                            'nama_struktur_mk' => $operasional->nama_struktur_mk,
                            'mulai_berlaku' => $this->formatSemesterMulai($operasional),
                            'semester_mulai' => $this->formatSemesterMulai($operasional),
                        ];
                    })->values(),
                ]
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'id_jenis_kurikulum' => 'required|exists:ref_jenis_kurikulum,id',
            'tahun_kurikulum' => [
                'required',
                'digits:4',
            ],
            'is_aktif' => 'nullable|boolean',
            'kode_kurikulum' => [
                'nullable',
                'string',
                'max:50',
                'unique:kurikulum_induk,kode_kurikulum',
            ],
        ], [], [
            'tahun_kurikulum' => 'tahun kurikulum',
            'id_jenis_kurikulum' => 'jenis kurikulum',
        ]);

        $this->ensureUniqueCurriculumIdentity(
            $validated['id_prodi'],
            $validated['id_jenis_kurikulum'],
            $validated['tahun_kurikulum']
        );

        $prodi = Prodi::query()->select('id', 'kode_prodi')->findOrFail($validated['id_prodi']);
        $jenisKurikulum = RefJenisKurikulum::query()
            ->select('id', 'kode_jenis', 'nama_jenis_kurikulum')
            ->findOrFail($validated['id_jenis_kurikulum']);

        $validated['kode_kurikulum'] = $validated['kode_kurikulum']
            ?? $this->generateUniqueKurikulumCode(
                $validated['tahun_kurikulum'],
                $jenisKurikulum->kode_jenis,
                $prodi->kode_prodi
            );

        if (!array_key_exists('is_aktif', $validated)) {
            $validated['is_aktif'] = false;
        }

        // Kolom fisik tetap dipertahankan untuk kompatibilitas data lama dan sorting DB.
        $validated['nama_kurikulum'] = $this->composeStoredCurriculumName(
            $validated['tahun_kurikulum'],
            $jenisKurikulum->nama_jenis_kurikulum
        );

        $item = KurikulumInduk::create($validated)->load([
            'prodi:id,nama_prodi,jenjang_pendidikan,kode_prodi',
            'jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum,is_aktif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tahun kurikulum berhasil ditambahkan',
            'data' => $this->serializeKurikulumInduk($item),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $item = KurikulumInduk::findOrFail($id);

        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'id_jenis_kurikulum' => 'required|exists:ref_jenis_kurikulum,id',
            'tahun_kurikulum' => [
                'required',
                'digits:4',
            ],
            'is_aktif' => 'nullable|boolean',
            'kode_kurikulum' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('kurikulum_induk', 'kode_kurikulum')->ignore($item->id),
            ],
        ]);

        $this->ensureUniqueCurriculumIdentity(
            $validated['id_prodi'],
            $validated['id_jenis_kurikulum'],
            $validated['tahun_kurikulum'],
            $item->id
        );

        $prodi = Prodi::query()->select('id', 'kode_prodi')->findOrFail($validated['id_prodi']);
        $jenisKurikulum = RefJenisKurikulum::query()
            ->select('id', 'kode_jenis', 'nama_jenis_kurikulum')
            ->findOrFail($validated['id_jenis_kurikulum']);

        $validated['kode_kurikulum'] = $validated['kode_kurikulum']
            ?? $this->generateUniqueKurikulumCode(
                $validated['tahun_kurikulum'],
                $jenisKurikulum->kode_jenis,
                $prodi->kode_prodi,
                $item->id
            );

        if (!array_key_exists('is_aktif', $validated)) {
            $validated['is_aktif'] = $item->is_aktif;
        }

        $validated['nama_kurikulum'] = $this->composeStoredCurriculumName(
            $validated['tahun_kurikulum'],
            $jenisKurikulum->nama_jenis_kurikulum
        );

        $item->update($validated);
        $item->load([
            'prodi:id,nama_prodi,jenjang_pendidikan,kode_prodi',
            'jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum,is_aktif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tahun kurikulum berhasil diperbarui',
            'data' => $this->serializeKurikulumInduk($item),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $item = KurikulumInduk::withCount(['kurikulumOperasional', 'riwayatMahasiswa'])->findOrFail($id);

        if ($item->kurikulum_operasional_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun kurikulum tidak dapat dihapus karena masih memiliki struktur operasional.',
            ], 422);
        }

        if ($item->riwayat_mahasiswa_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun kurikulum tidak dapat dihapus karena masih dipakai mahasiswa.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tahun kurikulum berhasil dihapus',
        ]);
    }

    private function serializeKurikulumInduk(KurikulumInduk $item): array
    {
        return [
            'id' => $item->id,
            'id_prodi' => $item->id_prodi,
            'id_jenis_kurikulum' => $item->id_jenis_kurikulum,
            'nama_kurikulum' => $item->nama_kurikulum,
            'keterangan' => $item->nama_kurikulum,
            'kode_kurikulum' => $item->kode_kurikulum,
            'tahun_kurikulum' => $item->tahun_kurikulum,
            'is_aktif' => $item->is_aktif,
            'prodi' => $item->prodi
                ? "({$item->prodi->jenjang_pendidikan}) {$item->prodi->nama_prodi}"
                : null,
            'jenis_kurikulum' => $item->jenisKurikulum ? [
                'id' => $item->jenisKurikulum->id,
                'kode_jenis' => $item->jenisKurikulum->kode_jenis,
                'nama_jenis_kurikulum' => $item->jenisKurikulum->nama_jenis_kurikulum,
            ] : null,
            'mulai_berlaku' => $this->resolveMulaiBerlaku($item),
            'jumlah_struktur_operasional' => $item->kurikulum_operasional_count ?? $item->kurikulumOperasional->count(),
        ];
    }

    private function resolveMulaiBerlaku(KurikulumInduk $item): ?string
    {
        $operasionalPertama = $item->kurikulumOperasional
            ->filter(fn ($operasional) => $operasional->semesterMulai !== null)
            ->sortBy(fn ($operasional) => [
                $operasional->semesterMulai?->tahunAkademik?->tahun_akademik ?? '9999/9999',
                $operasional->semesterMulai?->nama_semester ?? 'ZZZ',
            ])
            ->first();

        return $operasionalPertama ? $this->formatSemesterMulai($operasionalPertama) : null;
    }

    private function formatSemesterMulai(object $operasional): ?string
    {
        if (!$operasional->semesterMulai || !$operasional->semesterMulai->tahunAkademik) {
            return null;
        }

        return trim($operasional->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $operasional->semesterMulai->nama_semester);
    }

    private function generateUniqueKurikulumCode(
        string $tahunKurikulum,
        string $kodeJenis,
        string $kodeProdi,
        ?string $ignoreId = null
    ): string {
        $sanitizedProdi = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $kodeProdi) ?? '');
        $baseCode = sprintf('%s-%s-%s', $tahunKurikulum, strtoupper($kodeJenis), $sanitizedProdi);
        $candidate = $baseCode;
        $counter = 1;

        while (
            KurikulumInduk::query()
                ->where('kode_kurikulum', $candidate)
                ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $counter++;
            $candidate = sprintf('%s-%02d', $baseCode, $counter);
        }

        return $candidate;
    }

    private function ensureUniqueCurriculumIdentity(
        string $prodiId,
        string $jenisKurikulumId,
        string $tahunKurikulum,
        ?string $ignoreId = null
    ): void {
        $exists = KurikulumInduk::query()
            ->where('id_prodi', $prodiId)
            ->where('id_jenis_kurikulum', $jenisKurikulumId)
            ->where('tahun_kurikulum', $tahunKurikulum)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'tahun_kurikulum' => ['Kombinasi prodi, jenis kurikulum, dan tahun kurikulum sudah digunakan.'],
            ]);
        }
    }

    private function composeStoredCurriculumName(string $tahunKurikulum, string $namaJenisKurikulum): string
    {
        return trim($tahunKurikulum . ' - ' . $namaJenisKurikulum);
    }
}
