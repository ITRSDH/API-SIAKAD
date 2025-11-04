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
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $credentials = $request->only('email', 'password');

            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !$user->status) {
                return response()->json(['error' => 'Account is inactive or does not exist.'], 401);
            }

            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Hapus refresh token lama
            RefreshTokenModel::where('user_id', $user->id)->delete();

            $refreshToken = $this->createRefreshToken($user->id, $request);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'access_token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                    'user' => $this->transformUser($user),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Login failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->status) {
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

    public function me()
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            return response()->json($this->transformUser($user));
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to retrieve user.', 'error' => $e->getMessage()], 500);
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
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
