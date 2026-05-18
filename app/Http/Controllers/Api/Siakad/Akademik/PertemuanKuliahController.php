<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\PertemuanKuliah;
use App\Models\MasterData\KelasKuliah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PertemuanKuliahController extends Controller
{
    public function index(string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::with('pertemuanKuliah')->find($id_kelas_kuliah);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kelas' => [
                    'id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                ],
                'pertemuan' => $kelas->pertemuanKuliah,
            ],
        ]);
    }

    public function store(Request $request, string $id_kelas_kuliah): JsonResponse
    {
        $rules = [
            'pertemuan_ke' => 'required|integer|min:1|max:16',
            'judul_pertemuan' => 'nullable|string|max:255',
            'tanggal_pertemuan' => 'nullable|date|after_or_equal:today',
            'materi' => 'nullable|string|max:255',
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:draft,terjadwal,selesai,dibatalkan',
        ];

        // Dynamic validation based on status
        $status = $request->input('status');
        if (in_array($status, ['terjadwal', 'selesai', 'dibatalkan'])) {
            $rules['tanggal_pertemuan'] = 'required|date|after_or_equal:today';
        }

        $validated = $request->validate($rules);

        // Business logic validation
        if ($validated['status'] === 'terjadwal') {
            // Terjadwal harus ada tanggal
            if (empty($validated['tanggal_pertemuan'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertemuan yang dijadwalkan harus memiliki tanggal pasti.',
                ], 422);
            }

            // Cek apakah bentrok dengan jadwal lain
            $existingSchedule = PertemuanKuliah::where('id_kelas_kuliah', $id_kelas_kuliah)
                ->where('status', 'terjadwal')
                ->where('tanggal_pertemuan', $validated['tanggal_pertemuan'])
                ->where('id', '!=', $request->input('id', '')) // Exclude current record if edit
                ->exists();

            if ($existingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sudah ada pertemuan lain di jadwal yang sama.',
                ], 422);
            }
        }

        $kelas = KelasKuliah::find($id_kelas_kuliah);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        $pertemuan = PertemuanKuliah::create([
            'id_kelas_kuliah' => $id_kelas_kuliah,
            'pertemuan_ke' => $validated['pertemuan_ke'],
            'judul_pertemuan' => $validated['judul_pertemuan'] ?? null,
            'tanggal_pertemuan' => $validated['tanggal_pertemuan'] ?? null,
            'materi' => $validated['materi'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pertemuan kuliah berhasil ditambahkan',
            'data' => $pertemuan,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pertemuan = PertemuanKuliah::find($id);
        if (!$pertemuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertemuan kuliah tidak ditemukan',
            ], 404);
        }

        $rules = [
            'judul_pertemuan' => 'nullable|string|max:255',
            'tanggal_pertemuan' => 'nullable|date|after_or_equal:today',
            'materi' => 'nullable|string|max:255',
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:draft,terjadwal,selesai,dibatalkan',
        ];

        // Dynamic validation based on status
        $status = $request->input('status');
        if (in_array($status, ['terjadwal', 'selesai', 'dibatalkan'])) {
            $rules['tanggal_pertemuan'] = 'required|date|after_or_equal:today';
        }

        $validated = $request->validate($rules);

        // Business logic validation
        if ($validated['status'] === 'terjadwal') {
            // Terjadwal harus ada tanggal
            if (empty($validated['tanggal_pertemuan'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertemuan yang dijadwalkan harus memiliki tanggal pasti.',
                ], 422);
            }

            // Cek apakah bentrok dengan jadwal lain
            $existingSchedule = PertemuanKuliah::where('id_kelas_kuliah', $pertemuan->id_kelas_kuliah)
                ->where('status', 'terjadwal')
                ->where('tanggal_pertemuan', $validated['tanggal_pertemuan'])
                ->where('id', '!=', $id) // Exclude current record
                ->exists();

            if ($existingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sudah ada pertemuan lain di jadwal yang sama.',
                ], 422);
            }
        }

        $pertemuan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pertemuan kuliah berhasil diperbarui',
            'data' => $pertemuan->fresh(),
        ]);
    }
}
