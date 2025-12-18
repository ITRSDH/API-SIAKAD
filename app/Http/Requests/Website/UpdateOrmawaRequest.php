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
            'kategori' => 'sometimes|required|string|max:100',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|file|max:2048',
        ];
    }
}
