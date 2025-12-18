<?php

namespace Database\Seeders;

use App\Models\Website\Galeri;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GaleriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generate 10.000 galeri items untuk performance testing
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 10.000 data galeri... ini memakan waktu beberapa detik\n";
        
        // Truncate table dulu untuk clear data lama
        DB::table('galeri')->truncate();
        echo "✓ Cleared existing galeri data\n";
        
        // Create 10.000 galeri items
        Galeri::factory(10000)->create();
        
        echo "✅ Berhasil membuat 10.000 data galeri!\n";
        echo "📊 Total records: " . Galeri::count() . "\n";
    }
}

