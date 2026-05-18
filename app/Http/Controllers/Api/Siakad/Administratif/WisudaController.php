<?php

namespace App\Http\Controllers\Api\Siakad\Administratif;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Kelulusan;
use App\Models\Akademik\PeriodeWisuda;
use App\Models\Akademik\PesertaWisuda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WisudaController extends Controller
{
    public function indexPeriode(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PeriodeWisuda::withCount('peserta')
                ->orderByDesc('tanggal_wisuda')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function showPeriode(string $id): JsonResponse
    {
        $periode = PeriodeWisuda::with(['peserta.mahasiswa:id,nim,nama_mahasiswa', 'peserta.kelulusan'])
            ->find($id);

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Periode wisuda tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $periode,
        ]);
    }

    public function storePeriode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_periode' => 'required|string|max:150',
            'tanggal_mulai_pendaftaran' => 'nullable|date',
            'tanggal_selesai_pendaftaran' => 'nullable|date|after_or_equal:tanggal_mulai_pendaftaran',
            'tanggal_wisuda' => 'required|date',
            'lokasi' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,dibuka,ditutup,selesai',
            'catatan' => 'nullable|string',
        ]);

        $periode = PeriodeWisuda::create([
            'nama_periode' => $validated['nama_periode'],
            'tanggal_mulai_pendaftaran' => $validated['tanggal_mulai_pendaftaran'] ?? null,
            'tanggal_selesai_pendaftaran' => $validated['tanggal_selesai_pendaftaran'] ?? null,
            'tanggal_wisuda' => $validated['tanggal_wisuda'],
            'lokasi' => $validated['lokasi'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Periode wisuda berhasil ditambahkan',
            'data' => $periode,
        ], 201);
    }

    public function updatePeriode(Request $request, string $id): JsonResponse
    {
        $periode = PeriodeWisuda::find($id);

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Periode wisuda tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'nama_periode' => 'sometimes|required|string|max:150',
            'tanggal_mulai_pendaftaran' => 'nullable|date',
            'tanggal_selesai_pendaftaran' => 'nullable|date|after_or_equal:tanggal_mulai_pendaftaran',
            'tanggal_wisuda' => 'sometimes|required|date',
            'lokasi' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,dibuka,ditutup,selesai',
            'catatan' => 'nullable|string',
        ]);

        $periode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode wisuda berhasil diperbarui',
            'data' => $periode->fresh(),
        ]);
    }

    public function indexPeserta(string $id_periode_wisuda): JsonResponse
    {
        $periode = PeriodeWisuda::find($id_periode_wisuda);

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Periode wisuda tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => PesertaWisuda::with([
                'mahasiswa:id,nim,nama_mahasiswa',
                'kelulusan:id,id_mahasiswa,tanggal_lulus,nomor_ijazah,status',
            ])
                ->where('id_periode_wisuda', $id_periode_wisuda)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function showPeserta(string $id): JsonResponse
    {
        $peserta = PesertaWisuda::with([
            'periodeWisuda',
            'mahasiswa:id,nim,nama_mahasiswa',
            'kelulusan:id,id_mahasiswa,tanggal_lulus,nomor_ijazah,status',
        ])->find($id);

        if (!$peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Peserta wisuda tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $peserta,
        ]);
    }

    public function storePeserta(Request $request, string $id_periode_wisuda): JsonResponse
    {
        $periode = PeriodeWisuda::find($id_periode_wisuda);

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Periode wisuda tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'tanggal_daftar' => 'nullable|date',
            'status' => 'nullable|in:draft,terdaftar,terverifikasi,hadir,batal',
            'status_validasi_administrasi' => 'nullable|in:belum,memenuhi,tidak_memenuhi',
            'nomor_peserta' => 'nullable|string|max:50',
            'catatan' => 'nullable|string',
        ]);

        $kelulusan = Kelulusan::where('id_mahasiswa', $validated['id_mahasiswa'])
            ->orderByDesc('generated_at')
            ->first();

        if (!$kelulusan) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa belum memiliki data kelulusan',
            ], 422);
        }

        $existing = PesertaWisuda::where('id_mahasiswa', $validated['id_mahasiswa'])
            ->whereHas('periodeWisuda', function ($query) use ($periode) {
                $query->whereIn('status', ['draft', 'dibuka'])
                    ->where('id', '!=', $periode->id);
            })
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa masih terdaftar aktif pada periode wisuda lain',
            ], 422);
        }

        $peserta = PesertaWisuda::updateOrCreate(
            [
                'id_periode_wisuda' => $periode->id,
                'id_mahasiswa' => $validated['id_mahasiswa'],
            ],
            [
                'id_kelulusan' => $kelulusan->id,
                'tanggal_daftar' => $validated['tanggal_daftar'] ?? now()->toDateString(),
                'status' => $validated['status'] ?? 'terdaftar',
                'status_validasi_administrasi' => $validated['status_validasi_administrasi'] ?? 'belum',
                'nomor_peserta' => $validated['nomor_peserta'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Peserta wisuda berhasil disimpan',
            'data' => $peserta->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'kelulusan:id,id_mahasiswa,tanggal_lulus,nomor_ijazah,status',
            ]),
        ], 201);
    }

    public function updatePeserta(Request $request, string $id): JsonResponse
    {
        $peserta = PesertaWisuda::find($id);

        if (!$peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Peserta wisuda tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'tanggal_daftar' => 'nullable|date',
            'status' => 'nullable|in:draft,terdaftar,terverifikasi,hadir,batal',
            'status_validasi_administrasi' => 'nullable|in:belum,memenuhi,tidak_memenuhi',
            'nomor_peserta' => 'nullable|string|max:50',
            'catatan' => 'nullable|string',
        ]);

        $peserta->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Peserta wisuda berhasil diperbarui',
            'data' => $peserta->fresh()->load([
                'mahasiswa:id,nim,nama_mahasiswa',
                'kelulusan:id,id_mahasiswa,tanggal_lulus,nomor_ijazah,status',
            ]),
        ]);
    }
}
