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
        if (!Schema::hasColumn('kurikulum_induk', 'id_jenis_kurikulum')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->foreignUuid('id_jenis_kurikulum')
                    ->nullable()
                    ->after('id_prodi');
            });
        }

        if (!$this->hasForeignKey('kurikulum_induk', 'id_jenis_kurikulum')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->foreign('id_jenis_kurikulum')
                    ->references('id')
                    ->on('ref_jenis_kurikulum')
                    ->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('kurikulum_induk', 'tahun_kurikulum')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->string('tahun_kurikulum', 4)->nullable()->after('nama_kurikulum');
            });
        }

        if (!Schema::hasColumn('kurikulum_induk', 'kode_kurikulum')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->string('kode_kurikulum', 50)->nullable()->after('tahun_kurikulum');
            });
        }

        if (!Schema::hasColumn('kurikulum_induk', 'is_aktif')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->boolean('is_aktif')->default(false)->after('kode_kurikulum');
            });
        }

        $defaultJenisId = DB::table('ref_jenis_kurikulum')
            ->where('kode_jenis', 'INST')
            ->value('id');

        if (!$defaultJenisId) {
            $defaultJenisId = (string) Str::uuid();

            DB::table('ref_jenis_kurikulum')->insert([
                'id' => $defaultJenisId,
                'kode_jenis' => 'INST',
                'nama_jenis_kurikulum' => 'Kurikulum Institusi',
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $items = DB::table('kurikulum_induk')
            ->join('prodi', 'prodi.id', '=', 'kurikulum_induk.id_prodi')
            ->select('kurikulum_induk.id', 'kurikulum_induk.nama_kurikulum', 'prodi.kode_prodi')
            ->get();

        foreach ($items as $item) {
            $tahun = $this->extractYear((string) $item->nama_kurikulum) ?? (string) now()->format('Y');
            $kode = $this->buildKodeKurikulum($tahun, 'INST', (string) $item->kode_prodi, (string) $item->id);

            DB::table('kurikulum_induk')
                ->where('id', $item->id)
                ->update([
                    'id_jenis_kurikulum' => $defaultJenisId,
                    'tahun_kurikulum' => $tahun,
                    'kode_kurikulum' => $kode,
                    'is_aktif' => false,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('kurikulum_induk', function (Blueprint $table) {
            $table->foreignUuid('id_jenis_kurikulum')->nullable(false)->change();
            $table->string('tahun_kurikulum', 4)->nullable(false)->change();
            $table->string('kode_kurikulum', 50)->nullable(false)->change();
        });

        // Data lama dapat bertabrakan setelah kolom identitas baru diisi otomatis.
        // Rapikan dulu duplikatnya sebelum unique index diberlakukan.
        $this->deduplicateCurriculumIdentity();

        if (!$this->hasIndex('kurikulum_induk', 'kurikulum_induk_kode_unique')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->unique('kode_kurikulum', 'kurikulum_induk_kode_unique');
            });
        }

        if (!$this->hasIndex('kurikulum_induk', 'kurikulum_induk_prodi_jenis_tahun_unique')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->unique(
                    ['id_prodi', 'id_jenis_kurikulum', 'tahun_kurikulum'],
                    'kurikulum_induk_prodi_jenis_tahun_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('kurikulum_induk', 'kurikulum_induk_prodi_jenis_tahun_unique')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->dropUnique('kurikulum_induk_prodi_jenis_tahun_unique');
            });
        }

        if ($this->hasIndex('kurikulum_induk', 'kurikulum_induk_kode_unique')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->dropUnique('kurikulum_induk_kode_unique');
            });
        }

        if ($this->hasForeignKey('kurikulum_induk', 'id_jenis_kurikulum')) {
            Schema::table('kurikulum_induk', function (Blueprint $table) {
                $table->dropForeign(['id_jenis_kurikulum']);
            });
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('kurikulum_induk', 'id_jenis_kurikulum') ? 'id_jenis_kurikulum' : null,
            Schema::hasColumn('kurikulum_induk', 'tahun_kurikulum') ? 'tahun_kurikulum' : null,
            Schema::hasColumn('kurikulum_induk', 'kode_kurikulum') ? 'kode_kurikulum' : null,
            Schema::hasColumn('kurikulum_induk', 'is_aktif') ? 'is_aktif' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('kurikulum_induk', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function extractYear(string $value): ?string
    {
        if (preg_match('/(20\d{2})/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function buildKodeKurikulum(string $tahun, string $kodeJenis, string $kodeProdi, string $id): string
    {
        $sanitizedProdi = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $kodeProdi) ?? '');
        $suffix = strtoupper(substr(str_replace('-', '', $id), 0, 6));

        return sprintf('%s-%s-%s-%s', $tahun, strtoupper($kodeJenis), $sanitizedProdi, $suffix);
    }

    private function deduplicateCurriculumIdentity(): void
    {
        $duplicateGroups = DB::table('kurikulum_induk')
            ->select('id_prodi', 'id_jenis_kurikulum', 'tahun_kurikulum', DB::raw('COUNT(*) as total'))
            ->groupBy('id_prodi', 'id_jenis_kurikulum', 'tahun_kurikulum')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::transaction(function () use ($group) {
                $records = DB::table('kurikulum_induk')
                    ->where('id_prodi', $group->id_prodi)
                    ->where('id_jenis_kurikulum', $group->id_jenis_kurikulum)
                    ->where('tahun_kurikulum', $group->tahun_kurikulum)
                    ->orderByDesc('is_aktif')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                $primary = $records->first();
                if (!$primary) {
                    return;
                }

                $duplicateIds = $records
                    ->skip(1)
                    ->pluck('id')
                    ->filter();

                if ($duplicateIds->isEmpty()) {
                    return;
                }

                DB::table('kurikulum')
                    ->whereIn('id_kurikulum_induk', $duplicateIds->all())
                    ->update([
                        'id_kurikulum_induk' => $primary->id,
                        'updated_at' => now(),
                    ]);

                DB::table('kurikulum_induk')
                    ->where('id', $primary->id)
                    ->update([
                        'is_aktif' => $records->contains(fn ($record) => (bool) $record->is_aktif),
                        'updated_at' => now(),
                    ]);

                DB::table('kurikulum_induk')
                    ->whereIn('id', $duplicateIds->all())
                    ->delete();
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasForeignKey(string $table, string $columnName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $columnName)
            ->whereNotNull('referenced_table_name')
            ->exists();
    }
};
