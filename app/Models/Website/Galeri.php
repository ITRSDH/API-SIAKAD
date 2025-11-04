<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Galeri extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'galeri';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

   protected $fillable = [
        'judul',
        'kategori',
        'gambar',
        'deskripsi',
        'tanggal',
    ];

    protected $casts = [
        'gambar' => 'array',
        'tanggal' => 'date',
    ];
}
