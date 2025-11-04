<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'berita';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

   protected $fillable = [
        'judul',
        'isi',
        'kategori',
        'gambar',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
