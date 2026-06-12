<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\TugasAkhir;
use App\Models\Akademik\Transkrip;
use App\Models\Akademik\Yudisium;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use App\Services\ActiveCurriculumService;
use App\Services\AcademicPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YudisiumController extends Controller
{
    public function __construct(
        private readonly AcademicPolicyService $academicPolicyService,
        private readonly ActiveCurriculumService $activeCurriculumService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Yudisium::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'transkrip:id,id_mahasiswa,total_sks_lulus,ipk',
            'kurikulum:id,id_kurikulum_induk,nama_struktur_mk,jumlah_sks_lulus',
            'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
            'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
        ])->orderByDesc('generated_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn(Yudisium $yudisium) => $this->serializeYudisium($yudisium))->values(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $yudisium = Yudisium::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'transkrip.details',
            'kurikulum:id,id_kurikulum_induk,nama_struktur_mk,jumlah_sks_lulus',
            'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
            'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
        ])->find($id);

        if (!$yudisium) {
            return response()->json([
                'success' => false,
                'message' => 'Yudisium tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeYudisium($yudisium),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_kurikulum' => 'nullable|uuid|exists:kurikulum,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->buildYudisiumSnapshot($validated['id_mahasiswa'], $validated['id_kurikulum'] ?? null),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_kurikulum' => 'nullable|uuid|exists:kurikulum,id',
            'tanggal_yudisium' => 'nullable|date',
            'catatan' => 'nullable|string',
        ]);

        $snapshot = $this->buildYudisiumSnapshot($validated['id_mahasiswa'], $validated['id_kurikulum'] ?? null);

        $yudisium = Yudisium::updateOrCreate(
            [
                'id_mahasiswa' => $validated['id_mahasiswa'],
            ],
            [
                'id_transkrip' => $snapshot['summary']['id_transkrip'],
                'id_kurikulum' => $snapshot['summary']['id_kurikulum'],
                'target_sks_lulus' => $snapshot['summary']['target_sks_lulus'],
                'total_sks_lulus' => $snapshot['summary']['total_sks_lulus'],
                'ipk' => $snapshot['summary']['ipk'],
                'status' => $snapshot['summary']['status'],
                'predikat_lulus' => $snapshot['summary']['predikat_lulus'],
                'tanggal_yudisium' => $validated['tanggal_yudisium'] ?? now()->toDateString(),
                'catatan' => $validated['catatan'] ?? null,
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Yudisium berhasil digenerate',
            'data' => $this->serializeYudisium($yudisium->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'transkrip:id,id_mahasiswa,total_sks_lulus,ipk',
                'kurikulum:id,id_kurikulum_induk,nama_struktur_mk,jumlah_sks_lulus',
                'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
                'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            ])),
        ]);
    }

    private function buildYudisiumSnapshot(string $mahasiswaId, ?string $kurikulumId): array
    {
        $mahasiswa = Mahasiswa::with(['prodi', 'riwayatKurikulum.kurikulum'])->find($mahasiswaId);
        $resolvedKurikulumId = $kurikulumId ?: $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        $kurikulum = $kurikulumId
            ? Kurikulum::find($kurikulumId)
            : $this->activeCurriculumService->resolveActiveKurikulum($mahasiswa);
        $transkrip = Transkrip::with('details')->where('id_mahasiswa', $mahasiswaId)->first();

        if (!$mahasiswa || !$kurikulum || !$transkrip) {
            abort(404, 'Mahasiswa, kurikulum, atau transkrip tidak ditemukan');
        }

        if ($mahasiswa->id_prodi !== $kurikulum->id_prodi) {
            abort(422, 'Kurikulum tidak sesuai dengan program studi mahasiswa');
        }

        $yudisiumPolicy = $this->academicPolicyService->get('yudisium');
        if (($yudisiumPolicy['require_tugas_akhir_lulus'] ?? false) === true) {
            $tugasAkhir = TugasAkhir::query()
                ->where('id_mahasiswa', $mahasiswaId)
                ->orderByDesc('tanggal_lulus')
                ->orderByDesc('created_at')
                ->first();

            if (!$tugasAkhir || $tugasAkhir->status !== TugasAkhir::STATUS_LULUS) {
                abort(422, 'Mahasiswa belum lulus tugas akhir sehingga belum memenuhi syarat yudisium');
            }
        }

        $totalSksLulus = (int) $transkrip->total_sks_lulus;
        $targetSksLulus = (int) $kurikulum->jumlah_sks_lulus;
        $ipk = (float) $transkrip->ipk;
        $status = $totalSksLulus >= $targetSksLulus ? 'memenuhi' : 'belum_memenuhi';

        return [
            'summary' => [
                'id_mahasiswa' => $mahasiswaId,
                'id_transkrip' => $transkrip->id,
                'id_kurikulum' => $kurikulum->id,
                'id_struktur_operasional' => $kurikulum->id,
                'id_kurikulum_induk' => $kurikulum->id_kurikulum_induk,
                'target_sks_lulus' => $targetSksLulus,
                'total_sks_lulus' => $totalSksLulus,
                'ipk' => $ipk,
                'status' => $status,
                'predikat_lulus' => $status === 'memenuhi' ? $this->determinePredikat($ipk) : null,
                'kurikulum_context' => [
                    'id_kurikulum_induk' => $kurikulum->id_kurikulum_induk,
                    'id_struktur_operasional' => $kurikulum->id,
                    'kurikulum_induk' => $kurikulum->kurikulumInduk ? [
                        'id' => $kurikulum->kurikulumInduk->id,
                        'nama_kurikulum' => $kurikulum->kurikulumInduk->nama_kurikulum,
                        'keterangan' => $kurikulum->kurikulumInduk->nama_kurikulum,
                        'kode_kurikulum' => $kurikulum->kurikulumInduk->kode_kurikulum,
                        'tahun_kurikulum' => $kurikulum->kurikulumInduk->tahun_kurikulum,
                        'jenis_kurikulum' => $kurikulum->kurikulumInduk->jenisKurikulum ? [
                            'id' => $kurikulum->kurikulumInduk->jenisKurikulum->id,
                            'kode_jenis' => $kurikulum->kurikulumInduk->jenisKurikulum->kode_jenis,
                            'nama_jenis_kurikulum' => $kurikulum->kurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
                        ] : null,
                    ] : null,
                    'struktur_operasional' => [
                        'id' => $kurikulum->id,
                        'nama_struktur_mk' => $kurikulum->nama_struktur_mk,
                        'nama_kurikulum' => $kurikulum->nama_kurikulum,
                        'mulai_berlaku' => $kurikulum->semesterMulai?->tahunAkademik
                            ? trim($kurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $kurikulum->semesterMulai->nama_semester)
                            : null,
                    ],
                ],
            ],
        ];
    }

    private function serializeYudisium(Yudisium $yudisium): array
    {
        $yudisium->loadMissing('kurikulum.kurikulumInduk.jenisKurikulum');

        return [
            ...$yudisium->toArray(),
            'kurikulum_context' => [
                'id_kurikulum_induk' => $yudisium->kurikulum?->id_kurikulum_induk,
                'id_struktur_operasional' => $yudisium->id_kurikulum,
                'id_kurikulum_operasional' => $yudisium->id_kurikulum,
                'kurikulum_induk' => $yudisium->kurikulum?->kurikulumInduk ? [
                    'id' => $yudisium->kurikulum->kurikulumInduk->id,
                    'nama_kurikulum' => $yudisium->kurikulum->kurikulumInduk->nama_kurikulum,
                    'keterangan' => $yudisium->kurikulum->kurikulumInduk->nama_kurikulum,
                    'kode_kurikulum' => $yudisium->kurikulum->kurikulumInduk->kode_kurikulum,
                    'tahun_kurikulum' => $yudisium->kurikulum->kurikulumInduk->tahun_kurikulum,
                    'jenis_kurikulum' => $yudisium->kurikulum->kurikulumInduk->jenisKurikulum ? [
                        'id' => $yudisium->kurikulum->kurikulumInduk->jenisKurikulum->id,
                        'kode_jenis' => $yudisium->kurikulum->kurikulumInduk->jenisKurikulum->kode_jenis,
                        'nama_jenis_kurikulum' => $yudisium->kurikulum->kurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
                    ] : null,
                ] : null,
                'struktur_operasional' => $yudisium->kurikulum ? [
                    'id' => $yudisium->kurikulum->id,
                    'nama_struktur_mk' => $yudisium->kurikulum->nama_struktur_mk,
                    'nama_kurikulum' => $yudisium->kurikulum->nama_kurikulum,
                    'mulai_berlaku' => $yudisium->kurikulum->semesterMulai?->tahunAkademik
                        ? trim($yudisium->kurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $yudisium->kurikulum->semesterMulai->nama_semester)
                        : null,
                ] : null,
            ],
        ];
    }

    private function determinePredikat(float $ipk): string
    {
        return match (true) {
            $ipk >= 3.76 => 'Dengan Pujian',
            $ipk >= 3.51 => 'Sangat Memuaskan',
            default => 'Memuaskan',
        };
    }
}
