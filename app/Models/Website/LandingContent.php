<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingContent extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'landing_content';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        // Hero
        'hero_title',
        'hero_subtitle',
        'hero_background',

        // Statistik
        'jumlah_program_studi',
        'jumlah_mahasiswa',
        'jumlah_dosen',
        'jumlah_mitra',

        // Keunggulan
        'keunggulan',

        // Logo dan nama aplikasi
        'logo',
        'nama_aplikasi',

        // Footer
        'deskripsi_footer',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'youtube',
        'alamat',
        'telepon',
        'email',
    ];

    protected $casts = [
        'jumlah_program_studi' => 'integer',
        'jumlah_mahasiswa' => 'integer',
        'jumlah_dosen' => 'integer',
        'jumlah_mitra' => 'integer',
    ];

    // Mutators to handle null values
    public function setJumlahProgramStudiAttribute($value)
    {
        $this->attributes['jumlah_program_studi'] = $value ?? 0;
    }

    public function setJumlahMahasiswaAttribute($value)
    {
        $this->attributes['jumlah_mahasiswa'] = $value ?? 0;
    }

    public function setJumlahDosenAttribute($value)
    {
        $this->attributes['jumlah_dosen'] = $value ?? 0;
    }

    public function setJumlahMitraAttribute($value)
    {
        $this->attributes['jumlah_mitra'] = $value ?? 0;
    }

}
