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
            'jumlah_program_studi' => 'sometimes|nullable|integer|min:0',
            'jumlah_mahasiswa' => 'sometimes|nullable|integer|min:0',
            'jumlah_dosen' => 'sometimes|nullable|integer|min:0',
            'jumlah_mitra' => 'sometimes|nullable|integer|min:0',
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

    public function prepareForValidation()
    {
        // Only transform if the field is present in request
        $merge = [];
        if ($this->has('jumlah_program_studi')) {
            $merge['jumlah_program_studi'] = $this->jumlah_program_studi ?? 0;
        }
        if ($this->has('jumlah_mahasiswa')) {
            $merge['jumlah_mahasiswa'] = $this->jumlah_mahasiswa ?? 0;
        }
        if ($this->has('jumlah_dosen')) {
            $merge['jumlah_dosen'] = $this->jumlah_dosen ?? 0;
        }
        if ($this->has('jumlah_mitra')) {
            $merge['jumlah_mitra'] = $this->jumlah_mitra ?? 0;
        }
        
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
