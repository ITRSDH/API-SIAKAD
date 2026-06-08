<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['admin', 'kaprodi', 'dosen', 'mahasiswa', 'baak'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $historicalPermissions = [
            'akademik.krs-historical.filters',
            'akademik.krs-historical.eligible-mahasiswa',
            'akademik.krs-historical.package-classes',
            'akademik.krs-historical.preview.build',
            'akademik.krs-historical.execute.build',
            'akademik.krs-historical.preview.reopen',
            'akademik.krs-historical.execute.reopen',
            'akademik.krs-historical.preview.refinalize',
            'akademik.krs-historical.execute.refinalize',
            'akademik.krs-historical.preview.reset',
            'akademik.krs-historical.execute.reset',
            'akademik.krs-historical.preview.generate-khs',
            'akademik.krs-historical.execute.generate-khs',
            'akademik.krs-historical.batches',
            'akademik.krs-historical.batches.show',
        ];

        foreach ($historicalPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api',
            ]);
        }

        $rolesWithHistoricalAccess = ['admin', 'baak'];

        foreach ($rolesWithHistoricalAccess as $roleName) {
            $role = Role::findByName($roleName, 'api');
            $role->givePermissionTo($historicalPermissions);
        }
    }
}
