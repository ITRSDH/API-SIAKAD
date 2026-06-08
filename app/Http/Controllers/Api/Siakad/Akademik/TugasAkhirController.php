<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\TugasAkhir;
use App\Models\Akademik\TugasAkhirPembimbing;
use App\Models\Akademik\TugasAkhirUjian;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use App\Services\ActiveCurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TugasAkhirController extends Controller
{
    public function __construct(
        private readonly ActiveCurriculumService $activeCurriculumService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = TugasAkhir::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'kurikulum:id,id_kurikulum_induk,nama_struktur_mk',
            'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
            'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            'pembimbing.dosen:id,nama_dosen,nidn',
            'ujian',
        ])->orderByDesc('created_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn(TugasAkhir $item) => $this->serializeTugasAkhir($item))->values(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $tugasAkhir = TugasAkhir::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'kurikulum:id,id_kurikulum_induk,nama_struktur_mk',
            'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
            'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            'pembimbing.dosen:id,nama_dosen,nidn',
            'ujian',
        ])->find($id);

        if (!$tugasAkhir) {
            return response()->json([
                'success' => false,
                'message' => 'Data tugas akhir tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeTugasAkhir($tugasAkhir),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $this->ensureNoOtherActiveTaskAkhir($validated['id_mahasiswa']);

        $tugasAkhir = TugasAkhir::create($this->normalizePayload($validated));

        return response()->json([
            'success' => true,
            'message' => 'Data tugas akhir berhasil ditambahkan',
            'data' => $this->serializeTugasAkhir($tugasAkhir->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'kurikulum:id,id_kurikulum_induk,nama_struktur_mk',
                'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
                'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
            ])),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tugasAkhir = TugasAkhir::find($id);

        if (!$tugasAkhir) {
            return response()->json([
                'success' => false,
                'message' => 'Data tugas akhir tidak ditemukan',
            ], 404);
        }

        $validated = $this->validatePayload($request, true);

        if (($validated['is_active'] ?? false) === true) {
            $this->ensureNoOtherActiveTaskAkhir($validated['id_mahasiswa'] ?? $tugasAkhir->id_mahasiswa, $tugasAkhir->id);
        }

        $tugasAkhir->update($this->normalizePayload($validated, $tugasAkhir));

        return response()->json([
            'success' => true,
            'message' => 'Data tugas akhir berhasil diperbarui',
            'data' => $this->serializeTugasAkhir($tugasAkhir->fresh()->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'kurikulum:id,id_kurikulum_induk,nama_struktur_mk',
                'kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
                'kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
                'pembimbing.dosen:id,nama_dosen,nidn',
                'ujian',
            ])),
        ]);
    }

    public function syncPembimbing(Request $request, string $id): JsonResponse
    {
        $tugasAkhir = TugasAkhir::find($id);

        if (!$tugasAkhir) {
            return response()->json([
                'success' => false,
                'message' => 'Data tugas akhir tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'pembimbing' => 'required|array|min:1',
            'pembimbing.*.id_dosen' => 'required|uuid|exists:dosen,id',
            'pembimbing.*.peran' => 'required|in:pembimbing_1,pembimbing_2,co_pembimbing',
            'pembimbing.*.catatan' => 'nullable|string',
        ]);

        DB::transaction(function () use ($tugasAkhir, $validated) {
            TugasAkhirPembimbing::where('id_tugas_akhir', $tugasAkhir->id)->delete();

            foreach ($validated['pembimbing'] as $item) {
                TugasAkhirPembimbing::create([
                    'id_tugas_akhir' => $tugasAkhir->id,
                    'id_dosen' => $item['id_dosen'],
                    'peran' => $item['peran'],
                    'catatan' => $item['catatan'] ?? null,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pembimbing tugas akhir berhasil diperbarui',
            'data' => $tugasAkhir->fresh()->load('pembimbing.dosen:id,nama_dosen,nidn'),
        ]);
    }

    public function storeUjian(Request $request, string $id): JsonResponse
    {
        $tugasAkhir = TugasAkhir::find($id);

        if (!$tugasAkhir) {
            return response()->json([
                'success' => false,
                'message' => 'Data tugas akhir tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'jenis_ujian' => 'required|in:proposal,hasil,akhir',
            'tanggal_ujian' => 'required|date',
            'nilai_ujian' => 'nullable|numeric|min:0|max:100',
            'keputusan' => 'required|in:lulus,revisi,tidak_lulus',
            'catatan' => 'nullable|string',
        ]);

        $ujian = TugasAkhirUjian::create([
            'id_tugas_akhir' => $tugasAkhir->id,
            'jenis_ujian' => $validated['jenis_ujian'],
            'tanggal_ujian' => $validated['tanggal_ujian'],
            'nilai_ujian' => $validated['nilai_ujian'] ?? null,
            'keputusan' => $validated['keputusan'],
            'catatan' => $validated['catatan'] ?? null,
        ]);

        if ($validated['keputusan'] === 'lulus') {
            $tugasAkhir->update([
                'status' => TugasAkhir::STATUS_LULUS,
                'tanggal_lulus' => $validated['tanggal_ujian'],
                'is_active' => false,
            ]);
        } elseif ($validated['keputusan'] === 'revisi') {
            $tugasAkhir->update([
                'status' => TugasAkhir::STATUS_REVISI,
            ]);
        } else {
            $tugasAkhir->update([
                'status' => TugasAkhir::STATUS_TIDAK_LULUS,
                'is_active' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ujian tugas akhir berhasil ditambahkan',
            'data' => $ujian,
        ], 201);
    }

    public function updateUjian(Request $request, string $id): JsonResponse
    {
        $ujian = TugasAkhirUjian::find($id);

        if (!$ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Data ujian tugas akhir tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'jenis_ujian' => 'sometimes|required|in:proposal,hasil,akhir',
            'tanggal_ujian' => 'sometimes|required|date',
            'nilai_ujian' => 'nullable|numeric|min:0|max:100',
            'keputusan' => 'sometimes|required|in:lulus,revisi,tidak_lulus',
            'catatan' => 'nullable|string',
        ]);

        $ujian->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ujian tugas akhir berhasil diperbarui',
            'data' => $ujian->fresh(),
        ]);
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return $request->validate([
            'id_mahasiswa' => $required . '|uuid|exists:mahasiswa,id',
            'id_kurikulum' => 'nullable|uuid|exists:kurikulum,id',
            'jenis_tugas_akhir' => $required . '|string|max:50',
            'judul' => $required . '|string|max:255',
            'topik' => 'nullable|string',
            'status' => [
                $required,
                Rule::in([
                    TugasAkhir::STATUS_DRAFT,
                    TugasAkhir::STATUS_PENGAJUAN,
                    TugasAkhir::STATUS_BIMBINGAN,
                    TugasAkhir::STATUS_UJIAN,
                    TugasAkhir::STATUS_REVISI,
                    TugasAkhir::STATUS_LULUS,
                    TugasAkhir::STATUS_TIDAK_LULUS,
                    TugasAkhir::STATUS_DIBATALKAN,
                ]),
            ],
            'tanggal_pengajuan' => 'nullable|date',
            'tanggal_mulai_bimbingan' => 'nullable|date',
            'tanggal_lulus' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'catatan' => 'nullable|string',
        ]);
    }

    private function normalizePayload(array $validated, ?TugasAkhir $existing = null): array
    {
        $mahasiswaId = $validated['id_mahasiswa'] ?? $existing?->id_mahasiswa;
        $mahasiswa = $mahasiswaId
            ? Mahasiswa::with(['prodi', 'kurikulum', 'riwayatKurikulumAktif.kurikulum'])->find($mahasiswaId)
            : null;

        $resolvedKurikulumId = $validated['id_kurikulum']
            ?? $existing?->id_kurikulum
            ?? $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);

        abort_if(!$mahasiswa, 422, 'Mahasiswa tidak ditemukan untuk data tugas akhir');
        abort_if(!$resolvedKurikulumId, 422, 'Mahasiswa belum memiliki kurikulum operasional');

        $kurikulum = Kurikulum::find($resolvedKurikulumId);

        abort_if(!$kurikulum, 422, 'Kurikulum tugas akhir tidak ditemukan');
        abort_if($kurikulum->id_prodi !== $mahasiswa->id_prodi, 422, 'Kurikulum tugas akhir tidak sesuai dengan program studi mahasiswa');

        $status = $validated['status'] ?? $existing?->status;
        $isTerminal = in_array($status, [
            TugasAkhir::STATUS_LULUS,
            TugasAkhir::STATUS_TIDAK_LULUS,
            TugasAkhir::STATUS_DIBATALKAN,
        ], true);

        return array_merge($validated, [
            'id_kurikulum' => $resolvedKurikulumId,
            'is_active' => $validated['is_active'] ?? ($isTerminal ? false : ($existing?->is_active ?? true)),
            'tanggal_lulus' => $status === TugasAkhir::STATUS_LULUS
                ? ($validated['tanggal_lulus'] ?? now()->toDateString())
                : ($validated['tanggal_lulus'] ?? $existing?->tanggal_lulus),
        ]);
    }

    private function serializeTugasAkhir(TugasAkhir $tugasAkhir): array
    {
        $tugasAkhir->loadMissing('kurikulum.kurikulumInduk.jenisKurikulum');

        return [
            ...$tugasAkhir->toArray(),
            'kurikulum_context' => [
                'id_kurikulum_induk' => $tugasAkhir->kurikulum?->id_kurikulum_induk,
                'id_struktur_operasional' => $tugasAkhir->id_kurikulum,
                'id_kurikulum_operasional' => $tugasAkhir->id_kurikulum,
                'kurikulum_induk' => $tugasAkhir->kurikulum?->kurikulumInduk ? [
                    'id' => $tugasAkhir->kurikulum->kurikulumInduk->id,
                    'nama_kurikulum' => $tugasAkhir->kurikulum->kurikulumInduk->nama_kurikulum,
                    'keterangan' => $tugasAkhir->kurikulum->kurikulumInduk->nama_kurikulum,
                    'kode_kurikulum' => $tugasAkhir->kurikulum->kurikulumInduk->kode_kurikulum,
                    'tahun_kurikulum' => $tugasAkhir->kurikulum->kurikulumInduk->tahun_kurikulum,
                    'jenis_kurikulum' => $tugasAkhir->kurikulum->kurikulumInduk->jenisKurikulum ? [
                        'id' => $tugasAkhir->kurikulum->kurikulumInduk->jenisKurikulum->id,
                        'kode_jenis' => $tugasAkhir->kurikulum->kurikulumInduk->jenisKurikulum->kode_jenis,
                        'nama_jenis_kurikulum' => $tugasAkhir->kurikulum->kurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
                    ] : null,
                ] : null,
                'struktur_operasional' => $tugasAkhir->kurikulum ? [
                    'id' => $tugasAkhir->kurikulum->id,
                    'nama_struktur_mk' => $tugasAkhir->kurikulum->nama_struktur_mk,
                    'nama_kurikulum' => $tugasAkhir->kurikulum->nama_kurikulum,
                    'mulai_berlaku' => $tugasAkhir->kurikulum->semesterMulai?->tahunAkademik
                        ? trim($tugasAkhir->kurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $tugasAkhir->kurikulum->semesterMulai->nama_semester)
                        : null,
                ] : null,
            ],
        ];
    }

    private function ensureNoOtherActiveTaskAkhir(string $mahasiswaId, ?string $exceptId = null): void
    {
        $query = TugasAkhir::query()
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('is_active', true);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        abort_if($query->exists(), 422, 'Mahasiswa masih memiliki tugas akhir aktif lainnya');
    }
}
