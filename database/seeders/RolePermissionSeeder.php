<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User; // Pastikan model User diimport

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['admin', 'kaprodi', 'dosen_pengampu', 'dosen_pa', 'mahasiswa', 'baak'];

        foreach ($roles as $role) {
            Role::create(['name' => $role, 'guard_name' => 'api']); // Tambahkan guard_name
        }
    }
}
