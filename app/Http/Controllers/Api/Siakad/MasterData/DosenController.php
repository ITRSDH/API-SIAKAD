<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DosenController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi prodi dan user
            $dosens = Dosen::with(['prodi', 'user'])->get();
            $dataprodi = Prodi::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Dosen',
                'data' => [
                    'dosen' => $dosens,
                    'prodi' => $dataprodi
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $dosen = Dosen::with(['prodi', 'user'])->find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Dosen',
                'data' => $dosen
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'nidn' => 'nullable|string|unique:dosen,nidn',
                'nup' => 'nullable|string|unique:dosen,nup',
                'nama_dosen' => 'required|string|max:255',
                'jenis_kelamin' => 'required|in:L,P',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'nullable|string|min:6',
            ]);

            $result = DB::transaction(function () use ($request) {
                $password = $request->filled('password')
                    ? Hash::make($request->password)
                    : Hash::make($request->tanggal_lahir ? date('dmY', strtotime($request->tanggal_lahir)) : 'password');

                $user = User::create([
                    'name' => $request->nama_dosen,
                    'email' => $request->email,
                    'password' => $password,
                    'status' => 'aktif',
                ]);

                $user->assignRole('dosen');

                $dosenData = $this->buildDosenPayload($request);
                $dosenData['user_id'] = $user->id;

                $dosen = Dosen::create($dosenData);

                return [
                    'user' => $user,
                    'dosen' => $dosen->fresh(['prodi', 'user']),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Dosen dan User berhasil dibuat.',
                'data' => [
                    'dosen' => $result['dosen'],
                    'user' => $result['user'],
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
                'message' => 'Terjadi kesalahan saat membuat dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $dosen = Dosen::with('user')->find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'nidn' => 'nullable|string|unique:dosen,nidn,' . $id,
                'nup' => 'nullable|string|unique:dosen,nup,' . $id,
                'nama_dosen' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                'email' => 'nullable|email|unique:users,email,' . $dosen->user_id,
                'password' => 'nullable|string|min:6',
            ]);

            $result = DB::transaction(function () use ($request, $dosen) {
                $dosenData = $this->buildDosenPayload($request);
                $dosen->update($dosenData);

                $shouldSyncUser = $request->hasAny(['nama_dosen', 'email', 'password']);

                if ($shouldSyncUser) {
                    if ($dosen->user) {
                        $userData = [];

                        if ($request->has('nama_dosen')) {
                            $userData['name'] = $request->nama_dosen;
                        }

                        if ($request->has('email')) {
                            $userData['email'] = $request->email;
                        }

                        if ($request->filled('password')) {
                            $userData['password'] = Hash::make($request->password);
                        }

                        if (!empty($userData)) {
                            $dosen->user->update($userData);
                        }
                    } else {
                        $password = $request->filled('password')
                            ? Hash::make($request->password)
                            : Hash::make($request->tanggal_lahir ?? $dosen->tanggal_lahir
                                ? date('dmY', strtotime($request->tanggal_lahir ?? $dosen->tanggal_lahir))
                                : 'password');

                        $user = User::create([
                            'name' => $request->input('nama_dosen', $dosen->nama_dosen),
                            'email' => $request->input('email'),
                            'password' => $password,
                            'status' => 'aktif',
                        ]);

                        $user->assignRole('dosen');
                        $dosen->update(['user_id' => $user->id]);
                    }
                }

                return $dosen->fresh(['prodi', 'user']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Dosen dan User berhasil diperbarui.',
                'data' => $result
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
                'message' => 'Terjadi kesalahan saat memperbarui dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function buildDosenPayload(Request $request): array
    {
        return $request->only([
            'id_prodi',
            'nidn',
            'nup',
            'nama_dosen',
            'jenis_kelamin',
            'tanggal_lahir',
            'alamat',
            'no_hp',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $dosen = Dosen::with('user')->find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            DB::transaction(function () use ($dosen) {
                $user = $dosen->user;

                $dosen->delete();

                if ($user) {
                    RefreshToken::query()
                        ->where('user_id', $user->id)
                        ->delete();

                    if (method_exists($user, 'syncRoles')) {
                        $user->syncRoles([]);
                    }

                    $user->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Dosen, user, dan refresh token terkait berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
