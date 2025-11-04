<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingContentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'hero_title' => 'sometimes|nullable|string|max:255',
            'hero_subtitle' => 'sometimes|nullable|string',
            'hero_background' => 'sometimes|nullable|file|max:2048',
            'jumlah_program_studi' => 'sometimes|nullable|integer',
            'jumlah_mahasiswa' => 'sometimes|nullable|integer',
            'jumlah_dosen' => 'sometimes|nullable|integer',
            'jumlah_mitra' => 'sometimes|nullable|integer',
            'keunggulan' => 'sometimes|nullable|string',
            'logo' => 'sometimes|nullable|file|max:2048',
            'nama_aplikasi' => 'sometimes|nullable|string',
            'deskripsi_footer' => 'sometimes|nullable|string',
            'facebook' => 'sometimes|nullable|string',
            'twitter' => 'sometimes|nullable|string',
            'instagram' => 'sometimes|nullable|string',
            'linkedin' => 'sometimes|nullable|string',
            'youtube' => 'sometimes|nullable|string',
            'alamat' => 'sometimes|nullable|string',
            'telepon' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|string|email',
        ];
    }
}
