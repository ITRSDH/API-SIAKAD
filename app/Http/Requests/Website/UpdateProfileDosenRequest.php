<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileDosenRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:100',
            'nidn' => 'sometimes|required|string|max:100',
            'status' => 'sometimes|required|string|max:100',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'biografi' => 'nullable|string',
            'foto' => 'nullable|image|max:5120'
        ];
    }
}
