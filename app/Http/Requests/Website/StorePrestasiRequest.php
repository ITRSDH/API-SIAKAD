<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestasiRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama_mahasiswa' => 'required|string|max:255',
            'program_studi' => 'required|string|max:100',
            'judul_prestasi' => 'required|string|max:255',
            'tingkat' => 'required|string|max:100',
            'tahun' => 'required|integer',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|file|max:2048',
        ];
    }
}
