<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrestasiRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama_mahasiswa' => 'sometimes|required|string|max:255',
            'program_studi' => 'sometimes|required|string|max:100',
            'judul_prestasi' => 'sometimes|required|string|max:255',
            'tingkat' => 'sometimes|required|string|max:100',
            'tahun' => 'sometimes|required|integer',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|file|max:2048',
        ];
    }
}
