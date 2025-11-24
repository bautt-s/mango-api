<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateSubscriptionRequest
 * 
 * Archivo: app/Http/Requests/Subscriptions/CreateSubscriptionRequest.php
 */
class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                'string',
                'exists:plans,code',
            ],
            'preapproval_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_code.required' => 'El código del plan es obligatorio',
            'plan_code.exists' => 'El plan seleccionado no existe',
            'preapproval_id.string' => 'El ID de preaprobación debe ser una cadena de texto',
            'preapproval_id.max' => 'El ID de preaprobación no puede exceder 255 caracteres',
        ];
    }
}