<?php

namespace App\Http\Controllers\Api\Siakad\Krs;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRS;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KRSDosenWaliController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $activeSemester = $this->getActiveSemester();

        $query = KRS::with($this->krsRelations())
            ->whereHas('mahasiswa', function ($query) use ($dosen) {
                $query->where('id_dosen', $dosen->id);
            })
            ->orderByDesc('created_at');

        if ($activeSemester) {
            $query->where('id_semester', $activeSemester->id);
        }

        $krsList = $query->get()->map(fn(KRS $krs) => $this->transformKRSForDosen($krs));

        return response()->json([
            'success' => true,
            'data' => $krsList,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $krs = KRS::with($this->krsRelations())
            ->where('id', $id)
            ->whereHas('mahasiswa', function ($query) use ($dosen) {
                $query->where('id_dosen', $dosen->id);
            })
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformKRSForDosen($krs),
        ]);
    }

    public function getMahasiswaBimbingan(Request $request): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $mahasiswaList = Mahasiswa::with(['prodi'])
            ->where('id_dosen', $dosen->id)
            ->orderBy('nama_mahasiswa')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mahasiswaList,
        ]);
    }

    public function getKRSByMahasiswa(Request $request, string $mahasiswaId): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $mahasiswa = Mahasiswa::where('id', $mahasiswaId)
            ->where('id_dosen', $dosen->id)
            ->first();

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan atau bukan mahasiswa bimbingan Anda'
            ], 404);
        }

        $krsList = KRS::with($this->krsRelations())
            ->where('id_mahasiswa', $mahasiswaId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(KRS $krs) => $this->transformKRSForDosen($krs));

        return response()->json([
            'success' => true,
            'data' => $krsList,
        ]);
    }

    public function approve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
            'catatan' => 'nullable|string|max:500',
        ], [
            'id_krs.required' => 'ID KRS wajib diisi',
            'id_krs.uuid' => 'ID KRS harus berupa UUID',
            'id_krs.exists' => 'KRS tidak ditemukan',
            'catatan.string' => 'Catatan harus berupa string',
            'catatan.max' => 'Catatan maksimal 500 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $krs = $this->getKRSForDosen($request->id_krs, $dosen->id);

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan atau bukan mahasiswa bimbingan Anda'
            ], 404);
        }

        if ($krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS sudah disetujui sebelumnya'
            ], 400);
        }

        if ($krs->status_approval !== KRS::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya KRS yang sudah diajukan mahasiswa yang dapat disetujui'
            ], 400);
        }

        $validationSummary = $this->buildValidationSummary($krs);
        if (!$validationSummary['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'KRS belum valid untuk disetujui',
                'data' => $validationSummary,
            ], 400);
        }

        try {
            $krs = DB::transaction(function () use ($krs, $dosen, $request) {
                $krs->update([
                    'status_approval' => KRS::STATUS_APPROVED,
                    'approved_by' => $dosen->id,
                    'tanggal_approval' => now(),
                    'catatan' => $request->catatan,
                    'is_locked' => true,
                ]);

                return $krs;
            });

            $krs->refresh()->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil disetujui',
                'data' => $this->transformKRSForDosen($krs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui KRS: ' . $e->getMessage()
            ], 500);
        }
    }

    public function revision(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
            'catatan' => 'required|string|max:500',
        ], [
            'id_krs.required' => 'ID KRS wajib diisi',
            'id_krs.uuid' => 'ID KRS harus berupa UUID',
            'id_krs.exists' => 'KRS tidak ditemukan',
            'catatan.required' => 'Catatan revisi wajib diisi',
            'catatan.string' => 'Catatan harus berupa string',
            'catatan.max' => 'Catatan maksimal 500 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $krs = $this->getKRSForDosen($request->id_krs, $dosen->id);

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan atau bukan mahasiswa bimbingan Anda'
            ], 404);
        }

        if ($krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS sudah dikunci, tidak dapat direvisi'
            ], 400);
        }

        if ($krs->status_approval !== KRS::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya KRS dengan status pending yang dapat dikembalikan untuk revisi'
            ], 400);
        }

        try {
            $krs = DB::transaction(function () use ($krs, $dosen, $request) {
                $krs->update([
                    'status_approval' => KRS::STATUS_REVISED,
                    'approved_by' => $dosen->id,
                    'tanggal_approval' => now(),
                    'catatan' => $request->catatan,
                    'is_locked' => false,
                ]);

                return $krs;
            });

            $krs->refresh()->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil dikembalikan untuk revisi',
                'data' => $this->transformKRSForDosen($krs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengembalikan KRS untuk revisi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
            'catatan' => 'required|string|max:500',
        ], [
            'id_krs.required' => 'ID KRS wajib diisi',
            'id_krs.uuid' => 'ID KRS harus berupa UUID',
            'id_krs.exists' => 'KRS tidak ditemukan',
            'catatan.required' => 'Catatan penolakan wajib diisi',
            'catatan.string' => 'Catatan harus berupa string',
            'catatan.max' => 'Catatan maksimal 500 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $krs = $this->getKRSForDosen($request->id_krs, $dosen->id);

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan atau bukan mahasiswa bimbingan Anda'
            ], 404);
        }

        if ($krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS sudah dikunci, tidak dapat ditolak'
            ], 400);
        }

        if ($krs->status_approval !== KRS::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya KRS dengan status pending yang dapat ditolak'
            ], 400);
        }

        try {
            $krs = DB::transaction(function () use ($krs, $dosen, $request) {
                $krs->update([
                    'status_approval' => KRS::STATUS_REJECTED,
                    'approved_by' => $dosen->id,
                    'tanggal_approval' => now(),
                    'catatan' => $request->catatan,
                    'is_locked' => true,
                ]);

                return $krs;
            });

            $krs->refresh()->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'KRS ditolak',
                'data' => $this->transformKRSForDosen($krs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak KRS: ' . $e->getMessage()
            ], 500);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $activeSemester = $this->getActiveSemester();
        $semesterFilter = function ($query) use ($activeSemester) {
            if ($activeSemester) {
                $query->where('id_semester', $activeSemester->id);
            }
        };

        $stats = [
            'total_mahasiswa_wali' => Mahasiswa::where('id_dosen', $dosen->id)->count(),
            'pending_approval' => KRS::whereHas('mahasiswa', fn($query) => $query->where('id_dosen', $dosen->id))
                ->where($semesterFilter)
                ->pending()
                ->count(),
            'approved_this_semester' => KRS::whereHas('mahasiswa', fn($query) => $query->where('id_dosen', $dosen->id))
                ->where($semesterFilter)
                ->approved()
                ->count(),
            'revised_this_semester' => KRS::whereHas('mahasiswa', fn($query) => $query->where('id_dosen', $dosen->id))
                ->where($semesterFilter)
                ->revised()
                ->count(),
            'rejected_this_semester' => KRS::whereHas('mahasiswa', fn($query) => $query->where('id_dosen', $dosen->id))
                ->where($semesterFilter)
                ->rejected()
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function getPendingKRS(Request $request): JsonResponse
    {
        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $activeSemester = $this->getActiveSemester();

        $query = KRS::with($this->krsRelations())
            ->where('status_approval', KRS::STATUS_PENDING)
            ->whereHas('mahasiswa', function ($query) use ($dosen) {
                $query->where('id_dosen', $dosen->id);
            })
            ->orderBy('tanggal_pengajuan');

        if ($activeSemester) {
            $query->where('id_semester', $activeSemester->id);
        }

        $pendingKRS = $query->get()->map(fn(KRS $krs) => $this->transformKRSForDosen($krs));

        return response()->json([
            'success' => true,
            'data' => $pendingKRS,
        ]);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'krs_ids' => 'required|array|min:1',
            'krs_ids.*' => 'required|uuid|exists:krs,id',
            'catatan' => 'nullable|string|max:500',
        ], [
            'krs_ids.required' => 'ID KRS wajib diisi',
            'krs_ids.array' => 'ID KRS harus berupa array',
            'krs_ids.min' => 'Pilih minimal 1 KRS',
            'krs_ids.*.required' => 'ID KRS wajib diisi',
            'krs_ids.*.uuid' => 'ID KRS harus berupa UUID',
            'krs_ids.*.exists' => 'KRS tidak ditemukan',
            'catatan.string' => 'Catatan harus berupa string',
            'catatan.max' => 'Catatan maksimal 500 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dosen = $this->getAuthenticatedDosen($request);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        try {
            $approvedCount = 0;
            $failed = [];

            DB::transaction(function () use ($request, $dosen, &$approvedCount, &$failed) {
                foreach ($request->krs_ids as $krsId) {
                    $krs = $this->getKRSForDosen($krsId, $dosen->id);
                    $validationSummary = $krs ? $this->buildValidationSummary($krs) : null;

                    if (
                        !$krs ||
                        $krs->status_approval !== KRS::STATUS_PENDING ||
                        $krs->is_locked ||
                        !$validationSummary['is_valid']
                    ) {
                        $failed[] = [
                            'id_krs' => $krsId,
                            'reason' => $validationSummary && !$validationSummary['is_valid']
                                ? 'KRS belum valid untuk disetujui'
                                : 'KRS tidak ditemukan atau status tidak valid',
                        ];
                        continue;
                    }

                    $krs->update([
                        'status_approval' => KRS::STATUS_APPROVED,
                        'approved_by' => $dosen->id,
                        'tanggal_approval' => now(),
                        'catatan' => $request->catatan,
                        'is_locked' => true,
                    ]);

                    $approvedCount++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses bulk approve selesai',
                'data' => [
                    'approved_count' => $approvedCount,
                    'failed_count' => count($failed),
                    'failed_krs' => $failed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan bulk approve: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getAuthenticatedDosen(Request $request): ?Dosen
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return Dosen::where('user_id', $user->id)->first();
    }

    private function getActiveSemester(): ?Semester
    {
        return Semester::whereRaw('LOWER(status) = ?', ['aktif'])->first();
    }

    private function krsRelations(): array
    {
        return [
            'mahasiswa.prodi',
            'semester.tahunAkademik',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            'details.kelasKuliah.jadwal',
            'details.kelasKuliah.dosen_pengajar.dosen',
            'approvedBy',
            'sksOverrideBy',
        ];
    }

    private function getKRSForDosen(string $krsId, string $dosenId): ?KRS
    {
        return KRS::with($this->krsRelations())
            ->where('id', $krsId)
            ->whereHas('mahasiswa', function ($query) use ($dosenId) {
                $query->where('id_dosen', $dosenId);
            })
            ->first();
    }

    private function transformKRSForDosen(KRS $krs): array
    {
        $validationSummary = $this->buildValidationSummary($krs);

        return [
            'id' => $krs->id,
            'status_approval' => $krs->status_approval,
            'tanggal_pengajuan' => $krs->tanggal_pengajuan,
            'tanggal_approval' => $krs->tanggal_approval,
            'catatan' => $krs->catatan,
            'total_sks' => $krs->total_sks,
            'is_locked' => $krs->is_locked,
            'can_approve' => !$krs->is_locked && $krs->status_approval === KRS::STATUS_PENDING && $validationSummary['is_valid'],
            'can_revision' => !$krs->is_locked && $krs->status_approval === KRS::STATUS_PENDING,
            'mahasiswa' => $krs->mahasiswa,
            'semester' => $krs->semester,
            'details' => $krs->details,
            'approved_by_detail' => $krs->approvedBy,
            'sks_override' => [
                'is_active' => (bool) $krs->is_sks_override,
                'reason' => $krs->sks_override_reason,
                'by' => $krs->sksOverrideBy,
                'at' => $krs->sks_override_at,
            ],
            'validation_summary' => $validationSummary,
            'created_at' => $krs->created_at,
            'updated_at' => $krs->updated_at,
        ];
    }

    private function buildValidationSummary(KRS $krs): array
    {
        $details = $krs->details;
        $totalSks = $krs->total_sks ?? 0;
        $maxSks = 24;

        return [
            'total_matkul' => $details->count(),
            'total_sks' => $totalSks,
            'max_sks_allowed' => $maxSks,
            'is_sks_override' => (bool) $krs->is_sks_override,
            'sks_override_reason' => $krs->sks_override_reason,
            'remaining_sks' => max($maxSks - $totalSks, 0),
            'has_items' => $details->count() > 0,
            'max_sks_ok' => $totalSks <= $maxSks || (bool) $krs->is_sks_override,
            'max_sks_actual_ok' => $totalSks <= $maxSks,
            'is_valid' => $details->count() > 0 && ($totalSks <= $maxSks || (bool) $krs->is_sks_override),
        ];
    }
}
