<?php

namespace App\Services\Khs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use Illuminate\Support\Collection;

class KhsCalculationService
{
    public function __construct(
        private readonly KhsRemarkService $remarkService
    ) {
    }

    public function calculateSummary(Collection $details): array
    {
        $totalSksDiambil = (int) $details->sum('sks');
        $totalSksLulus = (int) $details->where('status', 'lulus')->sum('sks');
        $totalBobotNilai = (float) $details->sum(function (array $detail) {
            if (array_key_exists('bobot_nilai', $detail) && $detail['bobot_nilai'] !== null) {
                return (float) $detail['bobot_nilai'];
            }

            return ((float) ($detail['mutu'] ?? 0)) * ((int) ($detail['sks'] ?? 0));
        });

        $ips = $totalSksDiambil > 0 ? round($totalBobotNilai / $totalSksDiambil, 2) : 0.0;

        return [
            'total_sks_diambil' => $totalSksDiambil,
            'total_sks_lulus' => $totalSksLulus,
            'total_mutu' => round($totalBobotNilai, 2),
            'ips' => $ips,
            'keterangan' => $this->remarkService->resolveFromIps($ips),
        ];
    }

    public function calculateSummaryFromKrsDetails(Collection $details): array
    {
        $normalized = $details
            ->filter(fn(KRSDetail $detail) => $detail->isCountedInKhs())
            ->map(function (KRSDetail $detail) {
                $mutu = $detail->resolveMutuValue();

                return [
                    'sks' => (int) $detail->sks,
                    'mutu' => $mutu,
                    'bobot_nilai' => $detail->resolveWeightedBobotNilaiValue(),
                    'status' => $detail->status,
                ];
            })
            ->values();

        return $this->calculateSummary($normalized);
    }

    public function calculateIpkForKhs(KHS $khs): float
    {
        $allDetails = $khs->mahasiswa
            ->khs()
            ->with('details')
            ->get()
            ->flatMap(function (KHS $item) {
                return $item->details->map(function ($detail) {
                    return [
                        'sks' => (int) $detail->sks,
                        'mutu' => $detail->mutu !== null ? (float) $detail->mutu : null,
                        'bobot_nilai' => $detail->bobot_nilai !== null ? (float) $detail->bobot_nilai : null,
                    ];
                });
            });

        return $this->calculateIpkFromNormalizedDetails($allDetails);
    }

    public function calculateIpkFromApprovedKrs(Collection $allApprovedKrs, string $semesterId): float
    {
        $orderedKrs = $allApprovedKrs
            ->sortBy(function (KRS $item) {
                $tahun = $item->semester?->tahunAkademik?->tahun_akademik ?? '0000/0000';
                $semester = strtolower($item->semester?->nama_semester ?? '');

                return $tahun . '-' . ($semester === 'ganjil' ? '1' : '2');
            })
            ->values();

        $targetIndex = $orderedKrs->search(fn(KRS $item) => $item->id_semester === $semesterId);
        if ($targetIndex === false) {
            $targetIndex = $orderedKrs->count() - 1;
        }

        $normalized = $orderedKrs
            ->take($targetIndex + 1)
            ->flatMap(function (KRS $krs) {
                return $krs->details
                    ->filter(fn(KRSDetail $detail) => $detail->isCountedInKhs())
                    ->map(function (KRSDetail $detail) {
                        $mutu = $detail->resolveMutuValue();

                        return [
                            'sks' => (int) $detail->sks,
                            'mutu' => $mutu,
                            'bobot_nilai' => $detail->resolveWeightedBobotNilaiValue(),
                        ];
                    });
            })
            ->values();

        return $this->calculateIpkFromNormalizedDetails($normalized);
    }

    private function calculateIpkFromNormalizedDetails(Collection $allDetails): float
    {
        $eligible = $allDetails->filter(fn(array $detail) => $detail['mutu'] !== null && $detail['sks'] > 0);
        $totalSks = (int) $eligible->sum('sks');
        $totalBobotNilai = (float) $eligible->sum(function (array $detail) {
            if ($detail['bobot_nilai'] !== null) {
                return $detail['bobot_nilai'];
            }

            return ((float) $detail['mutu']) * ((int) $detail['sks']);
        });

        return $totalSks > 0 ? round($totalBobotNilai / $totalSks, 2) : 0.0;
    }
}
