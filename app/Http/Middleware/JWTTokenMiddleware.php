<?php

namespace App\Http\Middleware;

use App\Models\RefreshToken as RefreshTokenModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWTTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                Log::warning('Token not provided');
                return response()->json(['error' => 'Token not provided'], 401);
            }

            // Set token ke JWTAuth sebelum authenticate
            JWTAuth::setToken($token);

            // Coba parse payload dulu
            try {
                $payload = JWTAuth::getPayload();
            } catch (JWTException $e) {
                Log::error('Failed to parse token payload', ['message' => $e->getMessage()]);
                return response()->json(['error' => 'Token is invalid'], 401);
            }

            // Authenticate user
            $user = JWTAuth::authenticate($token);

            if (!$user) {
                Log::warning('User not found for token');
                return response()->json(['error' => 'User not found'], 401);
            }

            if (!$user->status) {
                Log::warning('User is inactive', ['user_id' => $user->id]);
                return response()->json(['error' => 'Account is inactive'], 401);
            }

            try {
                JWTAuth::parseToken()->authenticate();
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                Log::warning('Token expired');
                return response()->json(['error' => 'Token expired'], 401);
            }

            // $tokenRecord = RefreshTokenModel::where('user_id', $user->id)
            //     ->where('revoked', false)
            //     ->where('used_at', null)
            //     ->where('expires_at', '>', now())
            //     ->first();

            // if (!$tokenRecord) {
            //     Log::warning('No valid refresh token found for user', ['user_id' => $user->id]);
            //     return response()->json(['error' => 'Your session has expired or is invalid. Please log in again.'], 401);
            // }

            // if (
            //     $tokenRecord->user_agent !== $request->userAgent() ||
            //     $tokenRecord->ip_address !== $request->ip()
            // ) {
            //     Log::warning('Session mismatch', [
            //         'expected_ua' => $tokenRecord->user_agent,
            //         'actual_ua' => $request->userAgent(),
            //         'expected_ip' => $tokenRecord->ip_address,
            //         'actual_ip' => $request->ip(),
            //     ]);
            //     $tokenRecord->update(['revoked' => true, 'revoked_at' => now()]);
            //     return response()->json(['error' => 'Session mismatch. Please log in again.'], 401);
            // }

            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        } catch (JWTException $e) {
            Log::error('JWT Exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Token is invalid'], 401);
        }

        return $next($request);
    }
}
