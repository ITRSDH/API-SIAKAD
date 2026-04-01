<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfileDosenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'nidn' => 'required|string|max:100|unique:profile_dosen,nidn',
            'status' => 'required|string|max:100',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'biografi' => 'nullable|string',
            'foto' => 'nullable|mimes:jpeg,png,jpg,webp|max:2048'
        ];
    }
}
