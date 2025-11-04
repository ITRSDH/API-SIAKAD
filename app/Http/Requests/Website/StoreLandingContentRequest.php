<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandingContentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',
            'hero_background' => 'nullable|file|max:2048',
            'jumlah_program_studi' => 'nullable|integer',
            'jumlah_mahasiswa' => 'nullable|integer',
            'jumlah_dosen' => 'nullable|integer',
            'jumlah_mitra' => 'nullable|integer',
            'keunggulan' => 'nullable|string',
            'logo' => 'nullable|file|max:2048',
            'nama_aplikasi' => 'nullable|string',
            'deskripsi_footer' => 'nullable|string',
            'facebook' => 'nullable|string',
            'twitter' => 'nullable|string',
            'instagram' => 'nullable|string',
            'linkedin' => 'nullable|string',
            'youtube' => 'nullable|string',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string',
            'email' => 'nullable|string|email',
        ];
    }
}
