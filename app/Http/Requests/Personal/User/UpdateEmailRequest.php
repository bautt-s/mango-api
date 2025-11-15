<?php

namespace App\Http\Requests\Personal\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser una dirección válida',
            'email.max' => 'El email no puede tener más de 255 caracteres',
            'email.unique' => 'Este email ya está en uso',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email',
        ];
    }
}