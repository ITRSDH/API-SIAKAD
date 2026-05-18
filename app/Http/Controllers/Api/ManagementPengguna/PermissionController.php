<?php

namespace App\Http\Controllers\Api\ManagementPengguna;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            ->filter(fn($route) => $route->getName())
            ->filter(function ($route) {
                $uri = $route->uri();
                $name = $route->getName();

                $middleware = $route->middleware();
                if (!in_array('check.role.permission', $middleware)) {
                    return false;
                }

                $skip = ['sanctum', 'storage'];

                foreach ($skip as $segment) {
                    if (str_contains($uri, $segment)) {
                        return false;
                    }
                }

                foreach ($skip as $segment) {
                    if (str_contains($name, $segment)) {
                        return false;
                    }
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

        foreach ($routes as $route) {
            if (!in_array($route['name'], $existingPermissions)) {
                Permission::updateOrCreate([
                    'name' => $route['name'],
                    'guard_name' => $route['guard_name']
                ]);
                $added++;
            }
        }

        $routeNames = $routes->pluck('name')->toArray();
        $toDelete = Permission::whereNotIn('name', $routeNames)->get();

        foreach ($toDelete as $permission) {
            $permission->delete();
            $removed++;
        }

        $this->assignDefaultRolePermissions();

        return response()->json([
            'message' => 'Sinkronisasi selesai',
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
            $blacklist = ['sanctum', 'api', 'storage'];

            foreach ($routes as $route) {
                $name = $route->getName();
                if (!$name) {
                    continue;
                }

                $firstSegment = explode('.', $name)[0];
                if (in_array($firstSegment, $blacklist)) {
                    continue;
                }

                $parts = explode('.', $name);
                $count = count($parts);

                if ($count < 2) {
                    continue;
                }

                $labels = array_map(fn($part) => ucfirst(str_replace('-', ' ', $part)), $parts);
                $url = url($route->uri());

                if ($count === 2) {
                    [$section, $item] = $labels;

                    $sidebar[$section][$section]['items'][] = [
                        'label' => "$section ($item)",
                        'route' => $name,
                        'url' => $url
                    ];
                    continue;
                }

                if ($count === 3) {
                    [$section, $menu, $item] = $labels;

                    $sidebar[$section][$menu]['items'][] = [
                        'label' => $item,
                        'route' => $name,
                        'url' => $url
                    ];
                    continue;
                }

                if ($count === 4) {
                    [$section, $menu, $sub, $item] = $labels;

                    $sidebar[$section][$menu]['sub'][$sub][] = [
                        'label' => "$sub ($item)",
                        'route' => $name,
                        'url' => $url
                    ];
                    continue;
                }

                if ($count >= 5) {
                    [$section, $menu, $sub, $item, $method] = $labels;

                    $sidebar[$section][$menu]['sub'][$sub][] = [
                        'label' => "$item ($method)",
                        'route' => $name,
                        'url' => $url
                    ];
                }
            }

            $result = [];

            foreach ($sidebar as $section => $menus) {
                $menuArr = [];

                foreach ($menus as $menuName => $data) {
                    if (isset($data['items'])) {
                        $menuArr[] = [
                            'title' => $menuName,
                            'items' => $data['items']
                        ];
                        continue;
                    }

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
                            'sub' => $subArr
                        ];
                    }
                }

                $result[] = [
                    'section' => $section,
                    'menus' => $menuArr
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

    private function assignDefaultRolePermissions(): void
    {
        $defaultPermissionsByRole = [
            'dosen' => [
                'siakad.master.refrensi.kelas-kuliah.',
                'akademik.pertemuan.',
                'akademik.presensi.',
                'akademik.penilaian.',
                'akademik.krs-dosen.',
            ],
            'mahasiswa' => [
                'akademik.krs-mahasiswa.',
                'akademik.khs.',
                'akademik.transkrip.',
            ],
        ];

        foreach ($defaultPermissionsByRole as $roleName => $prefixes) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'api')
                ->first();

            if (!$role) {
                continue;
            }

            $permissions = Permission::where('guard_name', 'api')
                ->get()
                ->filter(function (Permission $permission) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        if (Str::startsWith($permission->name, $prefix)) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($permissions->isNotEmpty()) {
                $role->givePermissionTo($permissions);
            }
        }
    }
}
