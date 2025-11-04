<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGaleriRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'judul' => 'sometimes|required|string|max:255',
            'kategori' => 'sometimes|required|string|max:100',
            'gambar' => 'sometimes|required|file|max:1024',
            'deskripsi' => 'nullable|string',
            'tanggal' => 'sometimes|required|date',
        ];
    }
}
