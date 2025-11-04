<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrmawaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => 'sometimes|required|string|max:255',
            'deskripsi' => 'nullable|string',
            'gambar' => 'sometimes|required|file|max:2048',
            'kategori' => 'nullable|string|max:100',
        ];
    }
}
