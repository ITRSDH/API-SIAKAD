<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beasiswa';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nama',
        'kategori',
        'deskripsi',
        'gambar',
        'deadline',
        'kuota',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];
}
