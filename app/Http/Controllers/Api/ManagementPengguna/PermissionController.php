<?php

namespace App\Http\Controllers\Api\ManagementPengguna;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Route;
use Exception;

class PermissionController extends Controller
{
    public function index()
    {
        try {
            $permissions = Permission::all();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sinkronisasi permission dengan daftar route
     */
    public function sync()
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn($route) => $route->getName()) // hanya route yang punya nama
            ->filter(function ($route) {
                $uri = $route->uri();
                $name = $route->getName();

                // Cek apakah route memiliki middleware 'check.role.permission'
                $middleware = $route->middleware();
                if (!in_array('check.role.permission', $middleware)) {
                    return false;
                }

                // daftar yang ingin di-skip
                $skip = ['sanctum', 'storage'];

                // cek berdasarkan uri
                foreach ($skip as $s) {
                    if (str_contains($uri, $s)) return false;
                }

                // cek berdasarkan nama route
                foreach ($skip as $s) {
                    if (str_contains($name, $s)) return false;
                }

                return true;
            })
            ->map(fn($route) => [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'method' => implode('|', $route->methods()),
                'guard_name' => 'api'
            ]);

        $existingPermissions = Permission::pluck('name')->toArray();

        $added = 0;
        $removed = 0;

        // Tambahkan permission yang belum ada
        foreach ($routes as $r) {
            if (!in_array($r['name'], $existingPermissions)) {
                Permission::updateOrCreate([
                    'name' => $r['name'],
                    'guard_name' => $r['guard_name']
                ]);
                $added++;
            }
        }

        // Hapus permission yang sudah tidak ada route-nya
        $routeNames = $routes->pluck('name')->toArray();
        $toDelete = Permission::whereNotIn('name', $routeNames)->get();

        foreach ($toDelete as $perm) {
            $perm->delete();
            $removed++;
        }

        return response()->json([
            'message' => "✅ Sinkronisasi selesai",
            'added' => $added,
            'removed' => $removed,
            'total' => Permission::count(),
        ]);
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name',
            ]);

            $permission = Permission::create(['name' => $request->name]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully.',
                'data' => $permission
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $permission = Permission::findById($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $permission
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name,' . $id,
            ]);

            $permission = Permission::findById($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found.'
                ], 404);
            }

            $permission->update(['name' => $request->name]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully.',
                'data' => $permission
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $permission = Permission::findById($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found.'
                ], 404);
            }

            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSidebar()
    {
        try {
            $routes = Route::getRoutes();
            $sidebar = [];

            // Daftar prefix route yang tidak ingin dimunculkan
            $blacklist = ['sanctum', 'api', 'storage'];

            foreach ($routes as $route) {

                $name = $route->getName();
                if (!$name) continue;

                // Abaikan route yg termasuk blacklist
                $firstSegment = explode('.', $name)[0];
                if (in_array($firstSegment, $blacklist)) continue;

                $parts = explode('.', $name);
                $count = count($parts);

                if ($count < 2) continue;

                // Normalisasi label
                $labels = array_map(fn($p) => ucfirst(str_replace('-', ' ', $p)), $parts);

                $url = url($route->uri());

                if ($count == 2) {
                    // contoh: pengumuman.index
                    [$section, $item] = $labels;

                    $sidebar[$section][$section]['items'][] = [
                        'label' => "$section ($item)",
                        'route' => $name,
                        'url'   => $url
                    ];
                    continue;
                }

                if ($count == 3) {
                    // contoh: siakad.master.index
                    [$section, $menu, $item] = $labels;

                    $sidebar[$section][$menu]['items'][] = [
                        'label' => $item,
                        'route' => $name,
                        'url'   => $url
                    ];
                    continue;
                }

                if ($count == 4) {
                    // contoh: siakad.master.referensi.index
                    [$section, $menu, $sub, $item] = $labels;

                    $sidebar[$section][$menu]['sub'][$sub][] = [
                        'label' => "$sub ($item)",
                        'route' => $name,
                        'url'   => $url
                    ];
                    continue;
                }

                if ($count >= 5) {
                    [$section, $menu, $sub, $item, $method] = $labels;

                    $sidebar[$section][$menu]['sub'][$sub][] = [
                        'label' => "$item ($method)",
                        'route' => $name,
                        'url'   => $url
                    ];
                    continue;
                }
            }

            // Format final JSON
            $result = [];

            foreach ($sidebar as $section => $menus) {

                $menuArr = [];

                foreach ($menus as $menuName => $data) {

                    // Jika tidak ada sub → langsung tampilkan items
                    if (isset($data['items'])) {
                        $menuArr[] = [
                            'title' => $menuName,
                            'items' => $data['items']    // langsung, TANPA sub
                        ];
                        continue;
                    }

                    // Jika ada sub → tampilkan sub
                    if (isset($data['sub'])) {

                        $subArr = [];

                        foreach ($data['sub'] as $subName => $items) {
                            $subArr[] = [
                                'title' => $subName,
                                'items' => $items
                            ];
                        }

                        $menuArr[] = [
                            'title' => $menuName,
                            'sub'   => $subArr
                        ];
                    }
                }

                $result[] = [
                    'section' => $section,
                    'menus'   => $menuArr
                ];
            }

            return response()->json([
                'success' => true,
                'menu' => $result
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal membentuk sidebar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
