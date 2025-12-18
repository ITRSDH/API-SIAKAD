<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class StoreGaleriRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'judul' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'gambar' => 'required|file|max:2048',
            'deskripsi' => 'nullable|string',
            'tanggal' => 'required|date',
        ];
    }
}
