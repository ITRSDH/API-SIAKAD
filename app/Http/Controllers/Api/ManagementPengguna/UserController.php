<?php

namespace App\Http\Controllers\Api\ManagementPengguna;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\RefreshToken as RefreshTokenModel;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with('roles')->get();
            $role = Role::get();
            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'role' => $role
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to retrieve users.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::with('roles', 'permissions')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'User not found.', 'error' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6|confirmed',
                'status' => 'nullable|in:aktif,tidak-aktif',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => $request->status,
            ]);

            if ($request->roles) {
                $user->assignRole($request->roles);
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'data' => $user,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to create user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'password' => 'sometimes|min:6|confirmed',
                'status' => 'sometimes|in:aktif,tidak-aktif',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user->update($request->only(['name', 'email', 'status']));

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'data' => $user,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to update user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Hapus refresh token terkait user
            RefreshTokenModel::where('user_id', $id)->delete();

            // Baru hapus user
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
