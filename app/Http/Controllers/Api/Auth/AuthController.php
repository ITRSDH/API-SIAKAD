<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RefreshToken as RefreshTokenModel;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'username' => 'required', // email / nim / nup
                'password' => 'required',
            ], [
                'username.required' => 'Email / NIM / NUP wajib diisi.',
                'password.required' => 'Password wajib diisi.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $username = $request->username;
            $password = $request->password;

            // 🔍 Cari user berdasarkan email / nim / nup
            $user = User::where('email', $username)
                ->orWhereHas('mahasiswa', function ($q) use ($username) {
                    $q->where('nim', $username);
                })
                ->orWhereHas('dosen', function ($q) use ($username) {
                    $q->where('nup', $username);
                })
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Akun tidak ditemukan.'
                ], 404);
            }

            // Validasi status user
            if ($user->status !== 'aktif') {
                return response()->json([
                    'success' => false,
                    'error' => 'Akun belum aktif. Silakan hubungi admin.'
                ], 403);
            }

            // 🔐 Login tetap pakai email
            if (!$token = Auth::guard('api')->attempt([
                'email' => $user->email,
                'password' => $password
            ])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email / NIM / NUP atau password salah.'
                ], 401);
            }

            // Hapus refresh token lama
            RefreshTokenModel::where('user_id', $user->id)->delete();

            // Buat refresh token baru
            $refreshToken = $this->createRefreshToken($user->id, $request);

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'access_token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                    'user' => $this->transformUser($user),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || $user->status !== 'aktif') {
                return response()->json(['error' => 'User account is inactive or does not exist.'], 401);
            }

            // Ambil refresh token dari database berdasarkan user
            $tokenRecord = RefreshTokenModel::where('user_id', $user->id)
                ->where('revoked', false)
                ->where('used_at', null) // Belum pernah digunakan
                ->where('expires_at', '>', now())
                ->first();

            if (!$tokenRecord) {
                return response()->json(['error' => 'No valid or unused refresh token found. Please log in again.'], 401);
            }

            // Tandai refresh token sebagai sudah digunakan
            $tokenRecord->update(['used_at' => now()]);

            // Generate token baru
            $newToken = Auth::guard('api')->fromUser($user);

            // Generate refresh token baru
            $newRefreshToken = $this->createRefreshToken($user->id, $request);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully.',
                'data' => [
                    'access_token' => $newToken,
                    'refresh_token' => $newRefreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                    'user' => $this->transformUser($user),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Token refresh failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if ($user) {
                // Revoke semua refresh token user
                RefreshTokenModel::where('user_id', $user->id)
                    ->update(['revoked' => true, 'revoked_at' => now()]);
            }

            Auth::guard('api')->logout();

            return response()->json(['message' => 'Successfully logged out']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Logout failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Ambil data roles & permissions
            $roles = $user->roles->pluck('name');
            $permissions = $user->getAllPermissions()->pluck('name');

            // Inisialisasi data profil (dosen atau mahasiswa)
            $profile = null;
            $profileType = null;

            // Cek apakah user memiliki relasi ke dosen
            if ($user->relationLoaded('dosen') || $user->dosen) {
                $profile = $user->dosen;
                $profileType = 'dosen';
            }
            // Cek apakah user memiliki relasi ke mahasiswa
            elseif ($user->relationLoaded('mahasiswa') || $user->mahasiswa) {
                $profile = $user->mahasiswa;
                $profileType = 'mahasiswa';
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role' => $roles,
                    'permission' => $permissions,
                ],
                'profile_type' => $profileType,
                'profile' => $profile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createRefreshToken($userId, $request)
    {
        $refreshToken = Str::random(128);
        $expiresAt = now()->addDays(30);

        RefreshTokenModel::create([
            'user_id' => $userId,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        return $refreshToken;
    }

    private function transformUser($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            // 'roles' => $user->getRoleNames(),
            // 'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
