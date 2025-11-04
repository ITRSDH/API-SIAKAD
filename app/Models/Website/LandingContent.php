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

}
