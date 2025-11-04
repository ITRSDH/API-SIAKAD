<?php

namespace App\Http\Controllers\Api\ManagementPengguna;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Exception;

class RoleController extends Controller
{
    public function index()
    {
        try {
            $roles = Role::with('permissions')->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'permissions' => 'array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            $role = Role::create(['name' => $request->name]);
            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully.',
                'data' => $role
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $role = Role::with('permissions')->findById($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $role
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $id,
                'permissions' => 'array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            $role = Role::findById($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.'
                ], 404);
            }

            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully.',
                'data' => $role
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findById($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found.'
                ], 404);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
