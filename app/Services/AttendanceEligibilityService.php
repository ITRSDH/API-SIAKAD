<?php

namespace App\Services;

use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\PertemuanKuliah;
use App\Models\Akademik\PresensiKuliah;
use App\Models\MasterData\KelasKuliah;
use Illuminate\Support\Collection;

class AttendanceEligibilityService
{
    public function __construct(
        private readonly AcademicPolicyService $academicPolicyService
    ) {
    }

    public function summarizeForKrsDetail(KRSDetail $detail, ?float $minimumPercentage = null): array
    {
        $policy = $this->academicPolicyService->get('attendance');
        $statusWeights = $this->normalizeStatusWeights($policy['status_weights'] ?? []);
        $minimumPercentage ??= (float) ($policy['minimum_percentage'] ?? 75);

        $totalPertemuan = PertemuanKuliah::query()
            ->where('id_kelas_kuliah', $detail->id_kelas_kuliah)
            ->where('status', PertemuanKuliah::STATUS_SELESAI)
            ->count();

        $presensi = PresensiKuliah::query()
            ->where('id_krs_detail', $detail->id)
            ->whereHas('pertemuanKuliah', function ($query) use ($detail) {
                $query->where('id_kelas_kuliah', $detail->id_kelas_kuliah)
                    ->where('status', PertemuanKuliah::STATUS_SELESAI);
            })
            ->get();

        $totalHadir = $presensi->where('status_kehadiran', PresensiKuliah::STATUS_HADIR)->count();
        $totalEkuivalenHadir = round($presensi->sum(function (PresensiKuliah $item) use ($statusWeights) {
            return (float) ($statusWeights[$item->status_kehadiran] ?? 0);
        }), 2);

        $persentasePresensi = $totalPertemuan > 0
            ? round(($totalEkuivalenHadir / $totalPertemuan) * 100, 2)
            : 0.0;

        $isLayakPenilaian = $totalPertemuan > 0 && $persentasePresensi >= $minimumPercentage;

        return [
            'id_krs_detail' => $detail->id,
            'id_kelas_kuliah' => $detail->id_kelas_kuliah,
            'total_pertemuan' => $totalPertemuan,
            'total_hadir' => $totalHadir,
            'total_ekuivalen_hadir' => $totalEkuivalenHadir,
            'persentase_presensi' => $persentasePresensi,
            'minimum_presensi' => round($minimumPercentage, 2),
            'status_bobot_presensi' => $statusWeights,
            'is_layak_penilaian' => $isLayakPenilaian,
            'status_kelayakan' => $isLayakPenilaian ? 'layak' : 'tidak_layak',
            'catatan_kelayakan' => $this->buildKelayakanNote(
                $totalPertemuan,
                $persentasePresensi,
                $minimumPercentage,
                $isLayakPenilaian
            ),
        ];
    }

    public function summarizeForClass(KelasKuliah $kelas, ?float $minimumPercentage = null): array
    {
        $policy = $this->academicPolicyService->get('attendance');
        $minimumPercentage ??= (float) ($policy['minimum_percentage'] ?? 75);

        $details = $kelas->krsDetail()
            ->with('krs.mahasiswa')
            ->where('status', KRSDetail::STATUS_TERDAFTAR)
            ->get();

        $summaries = $details->map(function (KRSDetail $detail) use ($minimumPercentage) {
            $summary = $this->summarizeForKrsDetail($detail, $minimumPercentage);

            return array_merge($summary, [
                'mahasiswa' => [
                    'id' => $detail->krs?->mahasiswa?->id,
                    'nim' => $detail->krs?->mahasiswa?->nim,
                    'nama_mahasiswa' => $detail->krs?->mahasiswa?->nama_mahasiswa,
                ],
            ]);
        })->values();

        return [
            'kelas' => [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
            ],
            'minimum_presensi' => round($minimumPercentage, 2),
            'status_bobot_presensi' => $this->normalizeStatusWeights($policy['status_weights'] ?? []),
            'ringkasan' => [
                'total_mahasiswa' => $summaries->count(),
                'layak_penilaian' => $summaries->where('is_layak_penilaian', true)->count(),
                'tidak_layak_penilaian' => $summaries->where('is_layak_penilaian', false)->count(),
            ],
            'mahasiswa' => $summaries,
        ];
    }

    public function collectIneligibleForClass(KelasKuliah $kelas, ?float $minimumPercentage = null): Collection
    {
        $summary = $this->summarizeForClass($kelas, $minimumPercentage);

        return collect($summary['mahasiswa'])
            ->where('is_layak_penilaian', false)
            ->values();
    }

    private function normalizeStatusWeights(array $statusWeights): array
    {
        return [
            PresensiKuliah::STATUS_HADIR => (float) ($statusWeights[PresensiKuliah::STATUS_HADIR] ?? 1),
            PresensiKuliah::STATUS_IZIN => (float) ($statusWeights[PresensiKuliah::STATUS_IZIN] ?? 0),
            PresensiKuliah::STATUS_SAKIT => (float) ($statusWeights[PresensiKuliah::STATUS_SAKIT] ?? 0),
            PresensiKuliah::STATUS_ALPA => (float) ($statusWeights[PresensiKuliah::STATUS_ALPA] ?? 0),
        ];
    }

    private function buildKelayakanNote(
        int $totalPertemuan,
        float $persentasePresensi,
        float $minimumPercentage,
        bool $isLayakPenilaian
    ): string {
        if ($totalPertemuan === 0) {
            return 'Belum ada pertemuan selesai, kelayakan penilaian belum dapat dipenuhi.';
        }

        if ($isLayakPenilaian) {
            return sprintf(
                'Presensi %.2f%% memenuhi minimum %.2f%%.',
                $persentasePresensi,
                $minimumPercentage
            );
        }

        return sprintf(
            'Presensi %.2f%% belum memenuhi minimum %.2f%%.',
            $persentasePresensi,
            $minimumPercentage
        );
    }
}
