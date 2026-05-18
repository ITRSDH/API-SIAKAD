<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\PenilaianKelas;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KHSController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);

        if ($authenticatedMahasiswa) {
            $request->merge([
                'id_mahasiswa' => $authenticatedMahasiswa->id,
            ]);
        }

        $query = KHS::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'semester.tahunAkademik:id,tahun_akademik',
        ])->orderByDesc('generated_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        if ($request->filled('id_semester')) {
            $query->where('id_semester', $request->id_semester);
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
        $query = KHS::with([
            'mahasiswa:id,nim,nama_mahasiswa',
            'semester.tahunAkademik:id,tahun_akademik',
            'details',
        ]);

        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa(request());
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $khs = $query->find($id);

        if (!$khs) {
            return response()->json([
                'success' => false,
                'message' => 'KHS tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $khs,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'is_final' => 'nullable|boolean',
        ]);

        $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
        $semester = Semester::with('tahunAkademik')->find($validated['id_semester']);

        if (!$mahasiswa || !$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa atau semester tidak ditemukan',
            ], 404);
        }

        $krs = KRS::with([
            'details.kelasKuliah.penilaianKelas',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
        ])
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS approved untuk mahasiswa dan semester tersebut tidak ditemukan',
            ], 404);
        }

        if (!$krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS harus dalam kondisi terkunci sebelum KHS dapat digenerate',
            ], 422);
        }

        if ($validationError = $this->validateKrsForKhs($krs)) {
            return $validationError;
        }

        $existingKhs = KHS::where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->first();

        if ($existingKhs?->is_final) {
            return response()->json([
                'success' => false,
                'message' => 'KHS yang sudah difinalisasi tidak dapat digenerate ulang',
            ], 422);
        }

        $snapshot = $this->buildSemesterSnapshot($mahasiswa->id, $semester->id, $krs);

        $khs = DB::transaction(function () use ($validated, $snapshot, $mahasiswa, $semester) {
            $khs = KHS::updateOrCreate(
                [
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_semester' => $semester->id,
                ],
                [
                    'total_sks_diambil' => $snapshot['summary']['total_sks_diambil'],
                    'total_sks_lulus' => $snapshot['summary']['total_sks_lulus'],
                    'ips' => $snapshot['summary']['ips'],
                    'ipk' => $snapshot['summary']['ipk'],
                    'is_final' => $validated['is_final'] ?? false,
                    'generated_at' => now(),
                ]
            );

            KHSDetail::where('id_khs', $khs->id)->delete();

            foreach ($snapshot['details'] as $detail) {
                KHSDetail::create([
                    'id_khs' => $khs->id,
                    'id_krs_detail' => $detail['id_krs_detail'],
                    'id_kelas_kuliah' => $detail['id_kelas_kuliah'],
                    'id_mata_kuliah' => $detail['id_mata_kuliah'],
                    'kode_mk' => $detail['kode_mk'],
                    'nama_mk' => $detail['nama_mk'],
                    'sks' => $detail['sks'],
                    'nilai_akhir' => $detail['nilai_akhir'],
                    'nilai_huruf' => $detail['nilai_huruf'],
                    'bobot_nilai' => $detail['bobot_nilai'],
                    'status' => $detail['status'],
                ]);
            }

            return $khs->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'semester.tahunAkademik:id,tahun_akademik',
                'details',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'KHS berhasil digenerate',
            'data' => $khs,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_semester' => 'required|uuid|exists:semester,id',
        ]);

        $krs = KRS::with([
            'details.kelasKuliah.penilaianKelas',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            'semester.tahunAkademik',
        ])
            ->where('id_mahasiswa', $validated['id_mahasiswa'])
            ->where('id_semester', $validated['id_semester'])
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS approved untuk mahasiswa dan semester tersebut tidak ditemukan',
            ], 404);
        }

        if (!$krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS harus dalam kondisi terkunci sebelum KHS dapat dipreview',
            ], 422);
        }

        if ($validationError = $this->validateKrsForKhs($krs)) {
            return $validationError;
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildSemesterSnapshot($validated['id_mahasiswa'], $validated['id_semester'], $krs),
        ]);
    }

    private function buildSemesterSnapshot(string $mahasiswaId, string $semesterId, KRS $krs): array
    {
        $details = $this->collectCountedKhsDetails($krs->details)->map(function (KRSDetail $detail) {
            return [
                'id_krs_detail' => $detail->id,
                'id_kelas_kuliah' => $detail->id_kelas_kuliah,
                'id_mata_kuliah' => $detail->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id,
                'kode_mk' => $detail->kode_mata_kuliah,
                'nama_mk' => $detail->nama_mata_kuliah,
                'sks' => $detail->sks,
                'nilai_akhir' => $detail->nilai_akhir,
                'nilai_huruf' => $detail->nilai_huruf,
                'bobot_nilai' => $detail->bobot_nilai,
                'status' => $detail->status,
            ];
        })->values();

        $totalSksDiambil = (int) $details->sum('sks');
        $passed = $details->where('status', KRSDetail::STATUS_LULUS);
        $totalSksLulus = (int) $passed->sum('sks');

        $totalBobotSemester = 0;
        $totalSksBobotSemester = 0;
        foreach ($details as $detail) {
            if ($detail['bobot_nilai'] !== null) {
                $totalBobotSemester += ((float) $detail['bobot_nilai']) * ((int) $detail['sks']);
                $totalSksBobotSemester += (int) $detail['sks'];
            }
        }

        $ips = $totalSksBobotSemester > 0 ? round($totalBobotSemester / $totalSksBobotSemester, 2) : 0;
        $ipk = $this->calculateIPK($mahasiswaId, $semesterId);

        return [
            'summary' => [
                'id_mahasiswa' => $mahasiswaId,
                'id_semester' => $semesterId,
                'total_sks_diambil' => $totalSksDiambil,
                'total_sks_lulus' => $totalSksLulus,
                'ips' => $ips,
                'ipk' => $ipk,
            ],
            'details' => $details,
        ];
    }

    private function calculateIPK(string $mahasiswaId, string $semesterId): float
    {
        $allApprovedKrs = KRS::with(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah', 'semester.tahunAkademik'])
            ->with('details.kelasKuliah.penilaianKelas')
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->get()
            ->sortBy(function ($item) {
                $tahun = $item->semester?->tahunAkademik?->tahun_akademik ?? '0000/0000';
                $semester = strtolower($item->semester?->nama_semester ?? '');

                return $tahun . '-' . ($semester === 'ganjil' ? '1' : '2');
            })
            ->values();

        $targetIndex = $allApprovedKrs->search(fn($item) => $item->id_semester === $semesterId);
        if ($targetIndex === false) {
            $targetIndex = $allApprovedKrs->count() - 1;
        }

        $considered = $allApprovedKrs->take($targetIndex + 1);

        $totalBobot = 0;
        $totalSks = 0;

        foreach ($considered as $krs) {
            foreach ($this->collectCountedKhsDetails($krs->details) as $detail) {
                if ($detail->bobot_nilai !== null) {
                    $totalBobot += ((float) $detail->bobot_nilai) * ((int) $detail->sks);
                    $totalSks += (int) $detail->sks;
                }
            }
        }

        return $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0;
    }

    private function validateKrsForKhs(KRS $krs): ?JsonResponse
    {
        $details = $krs->details;

        $pendingDetails = $details
            ->filter(fn(KRSDetail $detail) => $detail->status === KRSDetail::STATUS_TERDAFTAR)
            ->values();

        if ($pendingDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada mata kuliah yang belum memiliki hasil studi final',
                'data' => [
                    'pending_krs_detail_ids' => $pendingDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        $countedDetails = $this->collectCountedKhsDetails($details);

        if ($countedDetails->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada hasil studi final yang dapat dimasukkan ke KHS',
            ], 422);
        }

        $unpublishedClassDetails = $countedDetails
            ->filter(function (KRSDetail $detail) {
                $workflow = $detail->kelasKuliah?->penilaianKelas;

                return !$workflow || !$workflow->isPublished();
            })
            ->values();

        if ($unpublishedClassDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada kelas dengan penilaian yang belum dipublikasikan',
                'data' => [
                    'unpublished_krs_detail_ids' => $unpublishedClassDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        $incompleteDetails = $countedDetails
            ->filter(fn(KRSDetail $detail) => !$detail->isFinalScored())
            ->values();

        if ($incompleteDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada nilai akhir yang belum lengkap',
                'data' => [
                    'incomplete_krs_detail_ids' => $incompleteDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        return null;
    }

    private function collectCountedKhsDetails(Collection $details): Collection
    {
        return $details
            ->filter(fn(KRSDetail $detail) => $detail->isCountedInKhs())
            ->values();
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
