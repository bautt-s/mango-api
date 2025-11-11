<?php

namespace App\Http\Requests\Configurations\Accounts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'sometimes|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i|max:9',
            'metadata' => 'nullable|array',
            'metadata.account_number' => 'nullable|string|max:255',
            'metadata.bank' => 'nullable|string|max:255',
            'metadata.account_type' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'label.max' => 'El nombre de la cuenta no puede exceder 255 caracteres',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF5733)',
        ];
    }
}
