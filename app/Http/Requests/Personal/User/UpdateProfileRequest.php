<?php

namespace App\Http\Requests\Personal\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'username' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:12',
            ],
            'timezone' => [
                'sometimes',
                'string',
                'timezone:all',
            ],
            'currency_code' => [
                'sometimes',
                'string',
                'size:3',
                'in:ARS,USD,EUR',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.string' => 'El nombre de usuario debe ser un texto válido',
            'username.max' => 'El nombre de usuario no puede tener más de 30 caracteres',
            'username.unique' => 'Este nombre de usuario ya está en uso',
            
            'phone.string' => 'El teléfono debe ser un texto válido',
            'phone.max' => 'El teléfono no puede tener más de 12 caracteres',
            
            'timezone.string' => 'La zona horaria debe ser un texto válido',
            'timezone.timezone' => 'La zona horaria no es válida',
            
            'currency_code.string' => 'El código de moneda debe ser un texto válido',
            'currency_code.size' => 'El código de moneda debe tener exactamente 3 caracteres',
            'currency_code.in' => 'El código de moneda debe ser ARS, USD o EUR',
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'nombre de usuario',
            'phone' => 'teléfono',
            'timezone' => 'zona horaria',
            'currency_code' => 'código de moneda',
        ];
    }
}