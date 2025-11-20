<?php

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMilestoneAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'required|array',
            'conditions.milestone_code' => [
                'required',
                'string',
                'max:100',
                Rule::in([
                    'first_transaction',
                    'transactions_10',
                    'transactions_50',
                    'transactions_100',
                    'budget_created',
                    'budget_met',
                    'savings_goal',
                    'category_organized',
                    'whatsapp_first',
                    'daily_streak_7',
                    'daily_streak_30',
                ]),
            ],
            'active' => 'boolean',
            'frequency' => ['required', Rule::in(['once', 'every_time'])],
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la alerta es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            
            'conditions.required' => 'Las condiciones de la alerta son obligatorias.',
            'conditions.array' => 'Las condiciones deben ser un objeto.',
            
            'conditions.milestone_code.required' => 'El código del hito es obligatorio.',
            'conditions.milestone_code.string' => 'El código del hito debe ser texto.',
            'conditions.milestone_code.in' => 'El código del hito no es válido.',
            
            'active.boolean' => 'El estado activo debe ser verdadero o falso.',
            
            'frequency.required' => 'La frecuencia es obligatoria.',
            'frequency.in' => 'La frecuencia debe ser "once" o "every_time" para alertas de hitos.',
            
            'metadata.array' => 'Los metadatos deben ser un objeto.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'conditions' => 'condiciones',
            'conditions.milestone_code' => 'código del hito',
            'active' => 'activo',
            'frequency' => 'frecuencia',
            'metadata' => 'metadatos',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default type
        $this->merge([
            'type' => 'milestone_reached',
        ]);
    }
}