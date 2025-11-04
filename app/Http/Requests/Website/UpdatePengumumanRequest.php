<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengumumanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'judul' => 'sometimes|required|string|max:255',
            'isi' => 'sometimes|required|string',
            'kategori' => 'nullable|string|max:100',
        ];
    }
}
