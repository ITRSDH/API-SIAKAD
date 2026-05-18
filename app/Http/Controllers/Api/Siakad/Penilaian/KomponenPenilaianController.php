<?php

namespace App\Http\Controllers\Api\Siakad\Penilaian;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KomponenPenilaian;
use App\Models\Akademik\PenilaianKelas;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KomponenPenilaianController extends Controller
{
    private const REQUIRED_TOTAL_BOBOT = 100.00;

    public function index(string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::with(['komponenPenilaian', 'penilaianKelas'])->find($id_kelas_kuliah);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kelas' => $kelas,
                'workflow_penilaian' => $this->formatWorkflow($this->getOrCreatePenilaianKelas($kelas)),
                'total_bobot' => round((float) $kelas->komponenPenilaian->where('is_active', true)->sum('bobot'), 2),
                'is_bobot_valid' => $this->isTotalBobotValid($kelas->id),
                'komponen' => $kelas->komponenPenilaian,
            ],
        ]);
    }

    public function store(Request $request, string $id_kelas_kuliah): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'bobot' => 'required|numeric|min:0.01|max:100',
            'urutan' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $kelas = KelasKuliah::with('dosen_pengajar')->find($id_kelas_kuliah);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas($request, $kelas)) {
            return $authorizationResponse;
        }

        if ($workflowResponse = $this->ensureEditableWorkflow($kelas)) {
            return $workflowResponse;
        }

        $totalBobot = (float) KomponenPenilaian::where('id_kelas_kuliah', $id_kelas_kuliah)
            ->where('is_active', true)
            ->sum('bobot');

        $bobotBaru = ($validated['is_active'] ?? true) ? (float) $validated['bobot'] : 0;

        if (($totalBobot + $bobotBaru) > self::REQUIRED_TOTAL_BOBOT) {
            return response()->json([
                'success' => false,
                'message' => 'Total bobot komponen tidak boleh melebihi 100%',
            ], 422);
        }

        $komponen = KomponenPenilaian::create([
            'id_kelas_kuliah' => $id_kelas_kuliah,
            'nama' => $validated['nama'],
            'bobot' => $validated['bobot'],
            'urutan' => $validated['urutan'] ?? ((int) KomponenPenilaian::where('id_kelas_kuliah', $id_kelas_kuliah)->max('urutan') + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komponen penilaian berhasil ditambahkan',
            'data' => $komponen,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $komponen = KomponenPenilaian::find($id);
        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen penilaian tidak ditemukan',
            ], 404);
        }

        $kelas = $komponen->kelasKuliah()->with('dosen_pengajar')->first();
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas($request, $kelas)) {
            return $authorizationResponse;
        }

        if ($workflowResponse = $this->ensureEditableWorkflow($kelas)) {
            return $workflowResponse;
        }

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'bobot' => 'sometimes|required|numeric|min:0.01|max:100',
            'urutan' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $bobotBaru = (float) ($validated['bobot'] ?? $komponen->bobot);
        $totalLain = (float) KomponenPenilaian::where('id_kelas_kuliah', $komponen->id_kelas_kuliah)
            ->where('id', '!=', $komponen->id)
            ->where('is_active', true)
            ->sum('bobot');
        $isActiveBaru = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $komponen->is_active;
        $effectiveBobotBaru = $isActiveBaru ? $bobotBaru : 0;

        if (($totalLain + $effectiveBobotBaru) > self::REQUIRED_TOTAL_BOBOT) {
            return response()->json([
                'success' => false,
                'message' => 'Total bobot komponen tidak boleh melebihi 100%',
            ], 422);
        }

        $komponen->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Komponen penilaian berhasil diperbarui',
            'data' => $komponen->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $komponen = KomponenPenilaian::find($id);
        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen penilaian tidak ditemukan',
            ], 404);
        }

        $kelas = $komponen->kelasKuliah()->with('dosen_pengajar')->first();
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas(request(), $kelas)) {
            return $authorizationResponse;
        }

        if ($workflowResponse = $this->ensureEditableWorkflow($kelas)) {
            return $workflowResponse;
        }

        $komponen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komponen penilaian berhasil dihapus',
        ]);
    }

    private function isTotalBobotValid(string $idKelasKuliah): bool
    {
        $totalBobotAktif = round((float) KomponenPenilaian::where('id_kelas_kuliah', $idKelasKuliah)
            ->where('is_active', true)
            ->sum('bobot'), 2);

        return abs($totalBobotAktif - self::REQUIRED_TOTAL_BOBOT) < 0.01;
    }

    private function getOrCreatePenilaianKelas(KelasKuliah $kelas): PenilaianKelas
    {
        $workflow = $kelas->penilaianKelas
            ?? PenilaianKelas::firstOrCreate(
                ['id_kelas_kuliah' => $kelas->id],
                ['status' => PenilaianKelas::STATUS_DRAFT]
            );

        $kelas->setRelation('penilaianKelas', $workflow);

        return $workflow;
    }

    private function ensureEditableWorkflow(KelasKuliah $kelas): ?JsonResponse
    {
        $workflow = $this->getOrCreatePenilaianKelas($kelas);

        if ($workflow->canManageDraftData()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Komponen penilaian tidak dapat diubah karena penilaian kelas sudah dipublikasikan',
            'data' => [
                'workflow_penilaian' => $this->formatWorkflow($workflow),
            ],
        ], 422);
    }

    private function formatWorkflow(PenilaianKelas $workflow): array
    {
        return [
            'id' => $workflow->id,
            'id_kelas_kuliah' => $workflow->id_kelas_kuliah,
            'status' => $workflow->status,
            'validated_at' => $workflow->validated_at,
            'published_at' => $workflow->published_at,
            'published_by' => $workflow->published_by,
            'reopened_at' => $workflow->reopened_at,
            'reopened_by' => $workflow->reopened_by,
            'reopen_reason' => $workflow->reopen_reason,
            'can_manage_draft_data' => $workflow->canManageDraftData(),
            'can_reopen' => $workflow->canBeReopened(),
        ];
    }

    private function authorizeManageKelas(Request $request, KelasKuliah $kelas): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Silakan login ulang.',
            ], 401);
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        $dosen = Dosen::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya dosen pengampu atau admin yang dapat mengelola komponen penilaian',
            ], 403);
        }

        $isPengampu = $kelas->dosen_pengajar
            ->contains(fn($pengampu) => $pengampu->id_registrasi_dosen === $dosen->id);

        if ($isPengampu) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Anda bukan dosen pengampu kelas ini',
        ], 403);
    }
}
