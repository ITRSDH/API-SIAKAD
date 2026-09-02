<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class StoreKelasKuliahRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'id_kurikulum_mata_kuliah' => 'required|uuid|exists:kurikulum_mata_kuliah,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'nama_kelas' => 'required|string|max:255',
            'kapasitas_peserta' => 'nullable|integer|min:1',
            'bahasan' => 'nullable|string|max:255',
            'lingkup' => 'nullable|in:internal,eksternal,campuran',
            'mode_kuliah' => 'nullable|in:offline,online,campuran',
            'tanggal_mulai_efektif' => 'nullable|date',
            'tanggal_akhir_efektif' => 'nullable|date|after_or_equal:tanggal_mulai_efektif',
        ];
    }
}
