<?php

namespace Tests\Feature;

use App\Models\MasterData\KelasKuliah;
use App\Services\KelasKuliahGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateKelasKuliahTest extends TestCase
{
    private array $prodi;

    private array $semester;

    private array $kurikulum;

    private array $mkIds;

    private array $kmkIds;

    protected function setUp(): void
    {
        parent::setUp();

        // Wrap seluruh test dalam satu transaksi (rollback di tearDown) agar tidak mencemari DB MySQL.
        DB::beginTransaction();

        $this->seedContext();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_candidates_menampilkan_mk_wajib_dan_pilihan(): void
    {
        $service = app(KelasKuliahGenerationService::class);

        $data = $service->candidates([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum' => $this->kurikulum['id'],
            'id_semester' => $this->semester['id'],
            'semester_ke' => 1,
            'default_kapasitas' => 40,
        ]);

        $this->assertSame(2, $data['summary']['total_items']);
        $this->assertSame(2, $data['summary']['will_create_count']);
        $this->assertSame(0, $data['summary']['duplicate_count']);
        $this->assertCount(2, $data['data']);
    }

    public function test_candidates_menandai_yang_sudah_memiliki_kelas_sebagai_duplicate(): void
    {
        // Buat kelas untuk salah satu MK -> harus jadi duplicate di candidate.
        KelasKuliah::create([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum_mata_kuliah' => $this->kmkIds[0],
            'id_semester' => $this->semester['id'],
            'nama_kelas' => '1A',
        ]);

        $service = app(KelasKuliahGenerationService::class);

        $data = $service->candidates([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum' => $this->kurikulum['id'],
            'id_semester' => $this->semester['id'],
            'semester_ke' => 1,
        ]);

        $this->assertSame(2, $data['summary']['total_items']);
        $this->assertSame(1, $data['summary']['will_create_count']);
        $this->assertSame(1, $data['summary']['duplicate_count']);

        $duplicate = collect($data['data'])->firstWhere('id_kurikulum_mata_kuliah', $this->kmkIds[0]);
        $this->assertSame('duplicate', $duplicate['status']);
    }

    public function test_create_membuat_kelas_untuk_mk_yang_dipilih(): void
    {
        $service = app(KelasKuliahGenerationService::class);

        $result = $service->create([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum' => $this->kurikulum['id'],
            'id_semester' => $this->semester['id'],
            'semester_ke' => 1,
            'rows' => [
                [
                    'id_kurikulum_mata_kuliah' => $this->kmkIds[0],
                    'nama_kelas' => '1A',
                    'kapasitas_peserta' => 40,
                ],
            ],
        ], 'test-user');

        $this->assertSame(1, $result['summary']['created_count']);
        $this->assertSame(0, $result['summary']['failed_count']);
        $this->assertSame('created', $result['results'][0]['status']);

        $this->assertDatabaseHas('kelas_kuliah', [
            'id_kurikulum_mata_kuliah' => $this->kmkIds[0],
            'nama_kelas' => '1A',
            'kapasitas_peserta' => 40,
        ]);
    }

    public function test_create_skip_mk_yang_sudah_punya_kelas(): void
    {
        KelasKuliah::create([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum_mata_kuliah' => $this->kmkIds[0],
            'id_semester' => $this->semester['id'],
            'nama_kelas' => '1A',
        ]);

        $service = app(KelasKuliahGenerationService::class);

        $result = $service->create([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum' => $this->kurikulum['id'],
            'id_semester' => $this->semester['id'],
            'semester_ke' => 1,
            'rows' => [
                [
                    'id_kurikulum_mata_kuliah' => $this->kmkIds[0],
                    'nama_kelas' => '1B',
                ],
            ],
        ], 'test-user');

        $this->assertSame(0, $result['summary']['created_count']);
        $this->assertSame(1, $result['summary']['skipped_count']);
        $this->assertSame('skipped', $result['results'][0]['status']);
    }

    public function test_create_mengabaikan_mk_di_luar_kurikulum_target(): void
    {
        $service = app(KelasKuliahGenerationService::class);

        $result = $service->create([
            'id_prodi' => $this->prodi['id'],
            'id_kurikulum' => $this->kurikulum['id'],
            'id_semester' => $this->semester['id'],
            'semester_ke' => 1,
            'rows' => [
                [
                    'id_kurikulum_mata_kuliah' => Str::uuid(),
                    'nama_kelas' => 'X',
                ],
            ],
        ], 'test-user');

        $this->assertSame(0, $result['summary']['total']);
        $this->assertSame(0, $result['summary']['created_count']);
    }

    private function seedContext(): void
    {
        $this->prodi = [
            'id' => (string) Str::uuid(),
            'kode_prodi' => 'T'.substr((string) Str::uuid(), 0, 4),
            'nama_prodi' => 'Prodi Test '.substr((string) Str::uuid(), 0, 4),
            'jenjang_pendidikan' => 'Sarjana',
        ];
        DB::table('prodi')->insert($this->prodi);

        $tahunAkademikId = (string) Str::uuid();
        DB::table('tahun_akademik')->insert([
            'id' => $tahunAkademikId,
            'tahun_akademik' => '2026/2027',
        ]);

        $this->semester = [
            'id' => (string) Str::uuid(),
            'id_tahun_akademik' => $tahunAkademikId,
            'nama_semester' => 'Ganjil',
            'kode_semester' => '20261',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2027-01-15',
            'status' => 'Aktif',
        ];
        DB::table('semester')->insert($this->semester);

        $this->mkIds = [];
        $mk1 = (string) Str::uuid();
        $mk2 = (string) Str::uuid();
        foreach ([$mk1, $mk2] as $i => $mkId) {
            DB::table('mata_kuliah')->insert([
                'id' => $mkId,
                'id_prodi' => $this->prodi['id'],
                'kode_mk' => 'MK'.($i + 1),
                'nama_mk' => 'Mata Kuliah '.($i + 1),
                'jenis_mk' => $i === 0 ? 'wajib_prodi' : 'pilihan',
                'kelompok_mk' => 'MKK',
                'sks' => 3,
            ]);
            $this->mkIds[] = $mkId;
        }
        $this->kmkIds = [];

        // Kolom tabel `kurikulum` berbeda antara DB live & repo (drift) —
        // insert dinamis agar hanya mengisi kolom yang benar-benar ada.
        $kurikulumColumns = Schema::getColumnListing('kurikulum');

        $this->kurikulum = [
            'id' => (string) Str::uuid(),
            'id_prodi' => $this->prodi['id'],
            'id_semester' => $this->semester['id'],
        ];

        foreach (['nama_struktur_mk', 'nama_kurikulum'] as $nameCol) {
            if (in_array($nameCol, $kurikulumColumns, true)) {
                $this->kurikulum[$nameCol] = 'Kurikulum Test '.substr((string) Str::uuid(), 0, 4);
            }
        }

        if (in_array('status', $kurikulumColumns, true)) {
            $this->kurikulum['status'] = 'Aktif';
        }

        if (in_array('is_locked', $kurikulumColumns, true)) {
            $this->kurikulum['is_locked'] = false;
        }

        $this->kurikulum['jumlah_sks_lulus'] = 144;
        $this->kurikulum['jumlah_sks_wajib'] = 130;
        $this->kurikulum['jumlah_sks_pilihan'] = 14;

        DB::table('kurikulum')->insert($this->kurikulum);

        foreach ($this->mkIds as $kmkIndex => $mkId) {
            $kmkId = (string) Str::uuid();
            DB::table('kurikulum_mata_kuliah')->insert([
                'id' => $kmkId,
                'id_kurikulum' => $this->kurikulum['id'],
                'id_mata_kuliah' => $mkId,
                'semester_ke' => 1,
                'status_mk' => $kmkIndex === 0 ? 'wajib' : 'pilihan',
                'is_wajib' => $kmkIndex === 0,
            ]);
            $this->kmkIds[] = $kmkId;
        }
    }
}
