<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class CheckRolePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Gunakan guard api (misalnya JWT)
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau sudah kadaluarsa.'
            ], 401);
        }

        // Cek jika user memiliki role admin, maka lewati semua pengecekan
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Ambil nama route yang sedang diakses
        $routeName = Route::currentRouteName();

        // Jika route belum punya nama, lewati
        if (!$routeName) {
            return $next($request);
        }

        // Jika permission belum disinkron di database, lewati (biar fleksibel saat dev)
        if (!Permission::where('name', $routeName)->exists()) {
            return $next($request);
        }

        // Cek apakah user memiliki izin
        if (!$user->can($routeName)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki izin untuk mengakses endpoint ini.',
                'route' => $routeName
            ], 403);
        }

        // Lolos
        return $next($request);
    }
}
