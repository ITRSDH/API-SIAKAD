<?php

namespace Database\Seeders;

use App\Models\Website\Beasiswa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BeasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 100 data beasiswa...\n";

        // Truncate existing data
        DB::table('beasiswa')->truncate();
        echo "✓ Cleared existing beasiswa data\n";

        // Create 100 beasiswa records
        Beasiswa::factory(100)->create();

        echo "✅ Berhasil membuat 100 data beasiswa!\n";

        // Get total records
        $total = DB::table('beasiswa')->count();
        echo "📊 Total records: $total\n\n";
    }
}
