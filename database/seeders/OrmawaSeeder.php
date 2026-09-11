<?php

namespace Database\Seeders;

use App\Models\Website\Ormawa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrmawaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n⏳ Sedang membuat 100 data ormawa...\n";

        // Truncate existing data
        DB::table('ormawa')->truncate();
        echo "✓ Cleared existing ormawa data\n";

        // Create 100 ormawa records
        Ormawa::factory(100)->create();

        echo "✅ Berhasil membuat 100 data ormawa!\n";

        // Get total records
        $total = DB::table('ormawa')->count();
        echo "📊 Total records: $total\n\n";
    }
}
