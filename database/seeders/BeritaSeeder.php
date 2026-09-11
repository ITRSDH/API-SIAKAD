<?php

namespace Database\Seeders;

use App\Models\Website\Berita;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BeritaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 100 data berita...\n";

        // Truncate existing data
        DB::table('berita')->truncate();
        echo "✓ Cleared existing berita data\n";

        // Create 100 berita records
        Berita::factory(100)->create();

        echo "✅ Berhasil membuat 100 data berita!\n";

        // Get total records
        $total = DB::table('berita')->count();
        echo "📊 Total records: $total\n\n";
    }
}
