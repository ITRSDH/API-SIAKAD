<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class RegisterKrsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'required|uuid|exists:mahasiswa,id',
        ];
    }

    public function messages()
    {
        return [
            'mahasiswa_ids.required' => 'Pilih minimal satu mahasiswa.',
            'mahasiswa_ids.array' => 'Format daftar mahasiswa tidak valid.',
            'mahasiswa_ids.min' => 'Pilih minimal satu mahasiswa.',
            'mahasiswa_ids.*.uuid' => 'ID mahasiswa harus berupa UUID.',
            'mahasiswa_ids.*.exists' => 'Mahasiswa yang dipilih tidak ditemukan.',
        ];
    }
}
