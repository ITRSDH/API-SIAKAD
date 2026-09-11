<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKelasKuliahCandidateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'id_kurikulum' => 'required|uuid|exists:kurikulum,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'semester_ke' => 'required|integer|min:1|max:14',
            'default_kapasitas' => 'nullable|integer|min:1',
        ];
    }
}
