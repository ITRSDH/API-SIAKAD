<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\PertemuanKuliah;
use App\Models\Akademik\PresensiKuliah;
use App\Models\MasterData\KelasKuliah;
use App\Services\AttendanceEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresensiKuliahController extends Controller
{
    public function __construct(
        private readonly AttendanceEligibilityService $attendanceEligibilityService
    ) {}

    public function index(string $id_pertemuan_kuliah): JsonResponse
    {
        $pertemuan = PertemuanKuliah::with([
            'kelasKuliah:id,nama_kelas',
            'presensi.krsDetail.krs.mahasiswa',
        ])->find($id_pertemuan_kuliah);

        if (!$pertemuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertemuan kuliah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pertemuan' => $pertemuan,
                'presensi' => $pertemuan->presensi->map(function (PresensiKuliah $item) {
                    return [
                        'id' => $item->id,
                        'id_krs_detail' => $item->id_krs_detail,
                        'status_kehadiran' => $item->status_kehadiran,
                        'catatan' => $item->catatan,
                        'mahasiswa' => [
                            'id' => $item->krsDetail?->krs?->mahasiswa?->id,
                            'nim' => $item->krsDetail?->krs?->mahasiswa?->nim,
                            'nama_mahasiswa' => $item->krsDetail?->krs?->mahasiswa?->nama_mahasiswa,
                        ],
                    ];
                })->values(),
            ],
        ]);
    }

    public function rekapKelas(string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::find($id_kelas_kuliah);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->attendanceEligibilityService->summarizeForClass($kelas),
        ]);
    }

    public function generatePeserta(string $id_pertemuan_kuliah): JsonResponse
    {
        $pertemuan = PertemuanKuliah::find($id_pertemuan_kuliah);
        if (!$pertemuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertemuan kuliah tidak ditemukan',
            ], 404);
        }

        $peserta = KRSDetail::with('krs.mahasiswa')
            ->where('id_kelas_kuliah', $pertemuan->id_kelas_kuliah)
            ->where('status', KRSDetail::STATUS_TERDAFTAR)
            ->get();

        DB::transaction(function () use ($peserta, $pertemuan) {
            foreach ($peserta as $detail) {
                PresensiKuliah::firstOrCreate(
                    [
                        'id_pertemuan_kuliah' => $pertemuan->id,
                        'id_krs_detail' => $detail->id,
                    ],
                    [
                        'status_kehadiran' => PresensiKuliah::STATUS_HADIR,
                    ]
                );
            }
        });

        return $this->index($pertemuan->id);
    }

    public function sync(Request $request, string $id_pertemuan_kuliah): JsonResponse
    {
        $pertemuan = PertemuanKuliah::find($id_pertemuan_kuliah);
        if (!$pertemuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertemuan kuliah tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'presensi' => 'required|array|min:1',
            'presensi.*.id_krs_detail' => 'required|uuid|exists:krs_detail,id',
            'presensi.*.status_kehadiran' => 'required|in:hadir,izin,sakit,alpa',
            'presensi.*.catatan' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $pertemuan) {
            foreach ($validated['presensi'] as $item) {
                $detail = KRSDetail::where('id', $item['id_krs_detail'])
                    ->where('id_kelas_kuliah', $pertemuan->id_kelas_kuliah)
                    ->first();

                if (!$detail) {
                    continue;
                }

                PresensiKuliah::updateOrCreate(
                    [
                        'id_pertemuan_kuliah' => $pertemuan->id,
                        'id_krs_detail' => $detail->id,
                    ],
                    [
                        'status_kehadiran' => $item['status_kehadiran'],
                        'catatan' => $item['catatan'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Presensi kuliah berhasil disimpan',
        ]);
    }
}
