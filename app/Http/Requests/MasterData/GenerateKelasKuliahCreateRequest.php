<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKelasKuliahCreateRequest extends FormRequest
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
            'rows' => 'required|array|min:1',
            'rows.*.id_kurikulum_mata_kuliah' => 'required|uuid|exists:kurikulum_mata_kuliah,id',
            'rows.*.nama_kelas' => 'required|string|max:255',
            'rows.*.kapasitas_peserta' => 'nullable|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'rows.required' => 'Pilih minimal satu mata kuliah untuk dibuat kelasnya.',
            'rows.min' => 'Pilih minimal satu mata kuliah untuk dibuat kelasnya.',
            'rows.*.nama_kelas.required' => 'Nama kelas wajib diisi untuk baris yang dipilih.',
        ];
    }
}
