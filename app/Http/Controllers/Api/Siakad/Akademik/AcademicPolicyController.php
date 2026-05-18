<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Services\AcademicPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicPolicyController extends Controller
{
    public function __construct(
        private readonly AcademicPolicyService $academicPolicyService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->academicPolicyService->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance.minimum_percentage' => 'nullable|numeric|min:0|max:100',
            'attendance.status_weights' => 'nullable|array',
            'attendance.status_weights.hadir' => 'nullable|numeric|min:0|max:1',
            'attendance.status_weights.izin' => 'nullable|numeric|min:0|max:1',
            'attendance.status_weights.sakit' => 'nullable|numeric|min:0|max:1',
            'attendance.status_weights.alpa' => 'nullable|numeric|min:0|max:1',
            'remedial.allowed_krs_detail_statuses' => 'nullable|array',
            'remedial.allowed_krs_detail_statuses.*' => 'in:terdaftar,drop,lulus,tidak_lulus',
            'remedial.max_attempts' => 'nullable|integer|min:1|max:10',
            'yudisium.require_tugas_akhir_lulus' => 'nullable|boolean',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kebijakan akademik berhasil diperbarui',
            'data' => $this->academicPolicyService->updateMany($validated),
        ]);
    }
}
