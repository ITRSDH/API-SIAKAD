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
            'jumlah_program_studi' => 'nullable|integer|min:0',
            'jumlah_mahasiswa' => 'nullable|integer|min:0',
            'jumlah_dosen' => 'nullable|integer|min:0',
            'jumlah_mitra' => 'nullable|integer|min:0',
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

    public function prepareForValidation()
    {
        $this->merge([
            'jumlah_program_studi' => $this->jumlah_program_studi ?? 0,
            'jumlah_mahasiswa' => $this->jumlah_mahasiswa ?? 0,
            'jumlah_dosen' => $this->jumlah_dosen ?? 0,
            'jumlah_mitra' => $this->jumlah_mitra ?? 0,
        ]);
    }
}
