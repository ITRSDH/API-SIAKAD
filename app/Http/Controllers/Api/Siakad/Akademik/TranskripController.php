<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KHS;
use App\Models\Akademik\Transkrip;
use App\Models\Akademik\TranskripDetail;
use App\Models\MasterData\Mahasiswa;
use App\Services\CurriculumConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranskripController extends Controller
{
    public function __construct(
        private readonly CurriculumConversionService $curriculumConversionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);

        if ($authenticatedMahasiswa) {
            $request->merge([
                'id_mahasiswa' => $authenticatedMahasiswa->id,
            ]);
        }

        $query = Transkrip::with('mahasiswa:id,nim,nama_mahasiswa')
            ->orderByDesc('generated_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'meta' => [
                'mahasiswa' => $authenticatedMahasiswa ? [
                    'id' => $authenticatedMahasiswa->id,
                    'nim' => $authenticatedMahasiswa->nim,
                    'nama_mahasiswa' => $authenticatedMahasiswa->nama_mahasiswa,
                ] : null,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $query = Transkrip::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'details',
        ]);

        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa(request());
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $transkrip = $query->find($id);

        if (!$transkrip) {
            return response()->json([
                'success' => false,
                'message' => 'Transkrip tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transkrip,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
        ]);

        if ($validationError = $this->validateTranscriptSource($validated['id_mahasiswa'])) {
            return $validationError;
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildTranscriptSnapshot($validated['id_mahasiswa']),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'is_final' => 'nullable|boolean',
        ]);

        $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan',
            ], 404);
        }

        if ($validationError = $this->validateTranscriptSource($mahasiswa->id)) {
            return $validationError;
        }

        $existingTranskrip = Transkrip::where('id_mahasiswa', $mahasiswa->id)->first();
        if ($existingTranskrip?->is_final) {
            return response()->json([
                'success' => false,
                'message' => 'Transkrip yang sudah difinalisasi tidak dapat digenerate ulang',
            ], 422);
        }

        $snapshot = $this->buildTranscriptSnapshot($mahasiswa->id);

        $transkrip = DB::transaction(function () use ($mahasiswa, $validated, $snapshot) {
            $transkrip = Transkrip::updateOrCreate(
                [
                    'id_mahasiswa' => $mahasiswa->id,
                ],
                [
                    'total_sks_lulus' => $snapshot['summary']['total_sks_lulus'],
                    'ipk' => $snapshot['summary']['ipk'],
                    'is_final' => $validated['is_final'] ?? false,
                    'generated_at' => now(),
                ]
            );

            TranskripDetail::where('id_transkrip', $transkrip->id)->delete();

            foreach ($snapshot['details'] as $detail) {
                TranskripDetail::create([
                    'id_transkrip' => $transkrip->id,
                    'id_khs_detail' => $detail['id_khs_detail'],
                    'id_krs_detail' => $detail['id_krs_detail'],
                    'id_mata_kuliah' => $detail['id_mata_kuliah'],
                    'kode_mk' => $detail['kode_mk'],
                    'nama_mk' => $detail['nama_mk'],
                    'sks' => $detail['sks'],
                    'nilai_akhir' => $detail['nilai_akhir'],
                    'nilai_huruf' => $detail['nilai_huruf'],
                    'bobot_nilai' => $detail['bobot_nilai'],
                    'status' => $detail['status'],
                    'semester_label' => $detail['semester_label'],
                ]);
            }

            return $transkrip->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'details',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Transkrip berhasil digenerate',
            'data' => $transkrip,
        ]);
    }

    private function buildTranscriptSnapshot(string $mahasiswaId): array
    {
        $khsList = KHS::with([
            'semester.tahunAkademik',
            'details',
        ])
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('is_final', true)
            ->orderBy('generated_at')
            ->get();

        $flattened = collect();

        foreach ($khsList as $khs) {
            $semesterLabel = trim(
                ($khs->semester?->tahunAkademik?->tahun_akademik ?? '') . ' ' .
                ($khs->semester?->nama_semester ?? '')
            );

            foreach ($khs->details as $detail) {
                if ($detail->status !== 'lulus') {
                    continue;
                }

                $canonicalCourse = $this->curriculumConversionService
                    ->resolveTranscriptCourse($mahasiswaId, $detail->id_mata_kuliah);

                $flattened->push([
                    'id_khs_detail' => $detail->id,
                    'id_krs_detail' => $detail->id_krs_detail,
                    'id_mata_kuliah' => $canonicalCourse?->id ?? $detail->id_mata_kuliah,
                    'kode_mk' => $canonicalCourse?->kode_mk ?? $detail->kode_mk,
                    'nama_mk' => $canonicalCourse?->nama_mk ?? $detail->nama_mk,
                    'sks' => (int) ($canonicalCourse?->sks ?? $detail->sks),
                    'nilai_akhir' => $detail->nilai_akhir,
                    'nilai_huruf' => $detail->nilai_huruf,
                    'bobot_nilai' => $detail->bobot_nilai,
                    'status' => 'lulus',
                    'semester_label' => $semesterLabel,
                ]);
            }
        }

        $bestPerCourse = $flattened
            ->filter(fn($item) => !empty($item['id_mata_kuliah']) || !empty($item['kode_mk']))
            ->groupBy(fn($item) => $item['id_mata_kuliah'] ?: $item['kode_mk'])
            ->map(function ($items) {
                return $items->sortByDesc(function ($item) {
                    return [
                        (float) ($item['bobot_nilai'] ?? 0),
                        (float) ($item['nilai_akhir'] ?? 0),
                    ];
                })->first();
            })
            ->values();

        $totalSksLulus = (int) $bestPerCourse->sum('sks');
        $totalBobot = $bestPerCourse->sum(function ($item) {
            return ((float) ($item['bobot_nilai'] ?? 0)) * ((int) ($item['sks'] ?? 0));
        });

        $ipk = $totalSksLulus > 0 ? round($totalBobot / $totalSksLulus, 2) : 0;

        return [
            'summary' => [
                'id_mahasiswa' => $mahasiswaId,
                'total_sks_lulus' => $totalSksLulus,
                'ipk' => $ipk,
            ],
            'details' => $bestPerCourse->values(),
        ];
    }

    private function validateTranscriptSource(string $mahasiswaId): ?JsonResponse
    {
        $hasFinalizedKhs = KHS::query()
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('is_final', true)
            ->exists();

        if (!$hasFinalizedKhs) {
            return response()->json([
                'success' => false,
                'message' => 'Transkrip belum dapat diproses karena belum ada KHS final',
            ], 422);
        }

        return null;
    }

    private function getAuthenticatedMahasiswa(Request $request): ?Mahasiswa
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return Mahasiswa::where('user_id', $user->id)->first();
    }
}
