<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'aktif',
        ]);

        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'status' => 'tidak-aktif',
        ]);
    }
}
