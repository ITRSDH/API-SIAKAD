<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileKampus extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'profile_kampus';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'judul',
        'deskripsi',
        'visi',
        'misi',
        'struktur_image',
        'fasilitas',
    ];
}
