<?php

namespace App\Http\Requests\Configurations\Accounts;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i|max:9',
            'currency_code' => 'required|string|size:3|in:ARS,USD,EUR',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
            'metadata.account_number' => 'nullable|string|max:255',
            'metadata.bank' => 'nullable|string|max:255',
            'metadata.account_type' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'El nombre de la cuenta es requerido',
            'label.max' => 'El nombre de la cuenta no puede exceder 255 caracteres',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF5733)',
            'currency_code.required' => 'El código de moneda es requerido',
            'currency_code.size' => 'El código de moneda debe tener exactamente 3 caracteres',
            'currency_code.in' => 'El código de moneda debe ser ARS, USD o EUR',
            'sort_order.min' => 'El orden no puede ser negativo',
        ];
    }
}