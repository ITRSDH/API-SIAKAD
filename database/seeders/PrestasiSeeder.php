<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Website\Prestasi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrestasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 100 data prestasi...\n";

        // Truncate existing data
        DB::table('prestasi')->truncate();
        echo "✓ Cleared existing prestasi data\n";

        // Create 100 prestasi records
        Prestasi::factory(100)->create();

        echo "✅ Berhasil membuat 100 data prestasi!\n";
        
        // Get total records
        $total = DB::table('prestasi')->count();
        echo "📊 Total records: $total\n\n";
    }
}
