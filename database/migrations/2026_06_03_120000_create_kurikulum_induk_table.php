<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum_induk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('nama_kurikulum');
            $table->timestamps();

            $table->unique(['id_prodi', 'nama_kurikulum'], 'kurikulum_induk_prodi_nama_unique');
        });

        Schema::table('kurikulum', function (Blueprint $table) {
            $table->renameColumn('nama_kurikulum', 'nama_struktur_mk');
        });

        Schema::table('kurikulum', function (Blueprint $table) {
            $table->foreignUuid('id_kurikulum_induk')
                ->nullable()
                ->after('id_prodi')
                ->constrained('kurikulum_induk')
                ->nullOnDelete();
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->foreignUuid('id_kurikulum_induk')
                ->nullable()
                ->after('id_kurikulum')
                ->constrained('kurikulum_induk')
                ->nullOnDelete();
        });

        $indukByKey = [];
        $now = now();

        $kurikulums = DB::table('kurikulum')
            ->select('id', 'id_prodi', 'nama_struktur_mk')
            ->orderBy('id_prodi')
            ->orderBy('nama_struktur_mk')
            ->get();

        foreach ($kurikulums as $kurikulum) {
            $operationalName = (string) $kurikulum->nama_struktur_mk;
            $normalizedName = $this->normalizeIndukName($operationalName);
            $key = $kurikulum->id_prodi . '|' . $normalizedName;

            if (!isset($indukByKey[$key])) {
                $indukId = (string) Str::uuid();

                DB::table('kurikulum_induk')->insert([
                    'id' => $indukId,
                    'id_prodi' => $kurikulum->id_prodi,
                    'nama_kurikulum' => $normalizedName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $indukByKey[$key] = $indukId;
            }

            DB::table('kurikulum')
                ->where('id', $kurikulum->id)
                ->update([
                    'id_kurikulum_induk' => $indukByKey[$key],
                    'updated_at' => $now,
                ]);
        }

        DB::statement('
            UPDATE mahasiswa
            INNER JOIN kurikulum ON kurikulum.id = mahasiswa.id_kurikulum
            SET mahasiswa.id_kurikulum_induk = kurikulum.id_kurikulum_induk
            WHERE mahasiswa.id_kurikulum IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['id_kurikulum_induk']);
            $table->dropColumn('id_kurikulum_induk');
        });

        Schema::table('kurikulum', function (Blueprint $table) {
            $table->dropForeign(['id_kurikulum_induk']);
            $table->dropColumn('id_kurikulum_induk');
        });

        Schema::table('kurikulum', function (Blueprint $table) {
            $table->renameColumn('nama_struktur_mk', 'nama_kurikulum');
        });

        Schema::dropIfExists('kurikulum_induk');
    }

    private function normalizeIndukName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        $patterns = [
            '/\s*-\s*\d{4}\/\d{4}\s+(ganjil|genap)$/i',
            '/\s*-\s*\d{4}\s*-\s*\d{4}\s+(ganjil|genap)$/i',
            '/\s*-\s*\d{4}\s+(ganjil|genap)$/i',
        ];

        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $normalized);
            if (is_string($candidate) && trim($candidate) !== '') {
                $normalized = trim($candidate);
                break;
            }
        }

        return $normalized;
    }
};
