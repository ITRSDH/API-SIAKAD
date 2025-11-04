<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFaqRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'pertanyaan' => 'sometimes|required|string|max:255',
            'jawaban' => 'sometimes|required|string',
        ];
    }
}
