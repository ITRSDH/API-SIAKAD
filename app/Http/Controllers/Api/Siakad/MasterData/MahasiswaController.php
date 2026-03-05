<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use App\Models\User;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Dosen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Exports\MahasiswaExport;
use App\Imports\MahasiswaImport;
use Maatwebsite\Excel\Facades\Excel;

class MahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan termasuk user
            $mahasiswas = Mahasiswa::with(['prodi', 'dosenWali', 'user'])->where('status', '!=', 'PMB')->get();
            $dataprodi = Prodi::all();
            $datadosen = Dosen::all();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mahasiswa',
                'data' => [
                    'mahasiswa'     => $mahasiswas,
                    'prodi'         => $dataprodi,
                    'dosen'         => $datadosen,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with(['prodi', 'dosenWali', 'user'])->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Mahasiswa',
                'data' => $mahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'nim' => 'required|string|max:20|unique:mahasiswa,nim',
                'nik' => 'nullable|string|max:20|unique:mahasiswa,nik',
                'nama_mahasiswa' => 'required|string|max:255',
                'jenis_kelamin' => 'nullable|in:L,P',
                'tempat_lahir' => 'nullable|string|max:255',
                'tanggal_lahir' => 'nullable|date',
                'tanggal_masuk' => 'nullable|date',
                'alamat' => 'nullable|string',
                'agama' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
                'status' => 'nullable|in:Aktif,Cuti,DO,Lulus',
                'angkatan' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
                'email' => 'nullable|email|unique:users,email',
                'password' => 'nullable|min:6'
            ]);

            // Gunakan transaksi untuk memastikan kedua data tersimpan atau gagal bersama
            $result = DB::transaction(function () use ($request) {
                // 1. Buat User terlebih dahulu
                $user = User::create([
                    'name' => $request->nama_mahasiswa,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'status' => $request->status === 'Aktif' ? 'aktif' : 'tidak-aktif'
                ]);

                // 2. Assign role "mahasiswa" ke user
                $user->assignRole('mahasiswa');

                // 3. Buat Mahasiswa dengan menghubungkan ke user yang baru dibuat
                $mahasiswaData = $request->except(['email', 'password']);
                $mahasiswaData['user_id'] = $user->id;

                $mahasiswa = Mahasiswa::create($mahasiswaData);

                return [
                    'user' => $user,
                    'mahasiswa' => $mahasiswa
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil dibuat.',
                'data' => [
                    'mahasiswa' => $result['mahasiswa'],
                    'user' => $result['user']
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with('user')->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'nim' => 'sometimes|string|max:20|unique:mahasiswa,nim,' . $id,
                'nik' => 'sometimes|string|max:20|unique:mahasiswa,nik,' . $id,
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'sometimes|date',
                'tempat_lahir' => 'sometimes|string|max:255',
                'tanggal_masuk' => 'sometimes|date',
                'alamat' => 'nullable|string',
                'agama' => 'sometimes|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
                'status' => 'sometimes|in:Aktif,Cuti,DO,Lulus',
                // 'angkatan' => 'sometimes|integer|min:1900|max:' . (date('Y') + 10),
                'email' => 'sometimes|email|unique:users,email,' . $mahasiswa->user_id,
                'password' => 'nullable|string|min:6'
            ]);

            // Gunakan transaksi untuk memastikan kedua data terupdate atau gagal bersama
            $result = DB::transaction(function () use ($request, $mahasiswa) {
                // 1. Update Mahasiswa
                $mahasiswaData = $request->except(['email', 'password']);
                $mahasiswa->update($mahasiswaData);

                // 2. Update User jika ada perubahan
                if ($mahasiswa->user) {
                    $userData = [];

                    if ($request->has('nama_mahasiswa')) {
                        $userData['name'] = $request->nama_mahasiswa;
                    }

                    if ($request->has('email')) {
                        $userData['email'] = $request->email;
                    }

                    // Hanya update password jika password diisi
                    if ($request->filled('password')) {
                        $userData['password'] = Hash::make($request->password);
                    }

                    // Sinkronisasi status: jika status mahasiswa berubah, update status user
                    if ($request->has('status')) {
                        $userData['status'] = $request->status === 'Aktif' ? 'aktif' : 'tidak-aktif';
                    }

                    if (!empty($userData)) {
                        $mahasiswa->user->update($userData);
                    }
                }

                return [
                    'mahasiswa' => $mahasiswa->fresh(['user'])
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil diperbarui.',
                'data' => $result['mahasiswa']
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with('user')->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            // Gunakan transaksi untuk memastikan kedua data terhapus atau gagal bersama
            DB::transaction(function () use ($mahasiswa) {
                // 1. Hapus Mahasiswa terlebih dahulu (karena memiliki foreign key ke User)
                $mahasiswa->delete();

                // 2. Hapus User terkait jika ada
                if ($mahasiswa->user) {
                    $mahasiswa->user->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $id_prodi = $request->get('id_prodi');
            $is_dummy = $request->get('is_dummy', false);

            $filename = 'data_mahasiswa_' . date('Y_m_d') . '.xlsx';

            return Excel::download(new MahasiswaExport($id_prodi, $is_dummy), $filename);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportTemplate(Request $request, $id_prodi = null)
    {
        try {
            $filename = 'template_import_mahasiswa_' . date('Y_m_d') . '.xlsx';

            return Excel::download(new MahasiswaExport($id_prodi, true), $filename);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat download template import mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request, $id_prodi): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:10240', // Max 10MB
            ]);

            $import = new MahasiswaImport($id_prodi);
            Excel::import($import, $request->file('file'));

            $errors = $import->getErrors();
            $successCount = $import->getSuccessCount();
            $rowCount = $import->getRowCount();

            $response = [
                'success' => true,
                'message' => 'Import data mahasiswa selesai.',
                'data' => [
                    'total_rows' => $rowCount,
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                    'errors' => $errors
                ]
            ];

            if (!empty($errors)) {
                $response['message'] = 'Import selesai dengan beberapa error. Lihat detail error di bawah.';
            }

            return response()->json($response, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
