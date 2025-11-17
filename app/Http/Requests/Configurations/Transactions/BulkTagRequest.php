<?php

namespace App\Http\Requests\Configurations\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class BulkTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tags' => 'required|array|max:10',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'tags.required' => 'Las etiquetas son obligatorias',
            'tags.array' => 'Las etiquetas deben ser un arreglo',
            'tags.max' => 'No puedes agregar más de 10 etiquetas',
            'tags.*.string' => 'Cada etiqueta debe ser texto',
            'tags.*.max' => 'Cada etiqueta no puede exceder 50 caracteres',
        ];
    }

    public function attributes(): array
    {
        return [
            'tags' => 'etiquetas',
        ];
    }
}
