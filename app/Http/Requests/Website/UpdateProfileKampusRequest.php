<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileKampusRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|nullable|string',
            'visi' => 'sometimes|nullable|string',
            'misi' => 'sometimes|nullable|string',
            'struktur_image' => 'sometimes|nullable|file|max:2048',
            'fasilitas' => 'sometimes|nullable|string',
        ];
    }
}
