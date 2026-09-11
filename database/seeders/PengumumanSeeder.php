<?php

namespace Database\Seeders;

use App\Models\Website\Pengumuman;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengumumanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 100 data pengumuman...\n";

        // Truncate existing data
        DB::table('pengumuman')->truncate();
        echo "✓ Cleared existing pengumuman data\n";

        // Create 100 pengumuman records
        Pengumuman::factory(100)->create();

        echo "✅ Berhasil membuat 100 data pengumuman!\n";

        // Get total records
        $total = DB::table('pengumuman')->count();
        echo "📊 Total records: $total\n\n";
    }
}
