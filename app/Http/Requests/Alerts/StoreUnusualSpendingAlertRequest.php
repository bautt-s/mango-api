<?php

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnusualSpendingAlertRequest extends FormRequest
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
            'conditions.category_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('user_id', $this->user()->id)
                            ->orWhere('is_system', true);
                    });
                }),
            ],
            'conditions.threshold_percentage' => 'nullable|integer|min:100|max:500',
            'conditions.lookback_days' => 'nullable|integer|min:7|max:90',
            'active' => 'boolean',
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'every_time'])],
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

            'conditions.category_id.uuid' => 'El ID de categoría debe ser un UUID válido.',
            'conditions.category_id.exists' => 'La categoría especificada no existe o no te pertenece.',

            'conditions.threshold_percentage.integer' => 'El porcentaje del umbral debe ser un número entero.',
            'conditions.threshold_percentage.min' => 'El porcentaje del umbral debe ser al menos 100.',
            'conditions.threshold_percentage.max' => 'El porcentaje del umbral no puede exceder 500.',

            'conditions.lookback_days.integer' => 'Los días de retrospectiva deben ser un número entero.',
            'conditions.lookback_days.min' => 'Los días de retrospectiva deben ser al menos 7.',
            'conditions.lookback_days.max' => 'Los días de retrospectiva no pueden exceder 90.',

            'active.boolean' => 'El estado activo debe ser verdadero o falso.',

            'frequency.required' => 'La frecuencia es obligatoria.',
            'frequency.in' => 'La frecuencia debe ser "daily", "weekly" o "every_time".',

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
            'conditions.category_id' => 'categoría',
            'conditions.threshold_percentage' => 'porcentaje del umbral',
            'conditions.lookback_days' => 'días de retrospectiva',
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
            'type' => 'unusual_spending',
        ]);

        // Set defaults for conditions if not provided
        if ($this->has('conditions')) {
            $conditions = $this->input('conditions', []);

            if (!isset($conditions['threshold_percentage'])) {
                $conditions['threshold_percentage'] = 150;
            }

            if (!isset($conditions['lookback_days'])) {
                $conditions['lookback_days'] = 30;
            }

            $this->merge(['conditions' => $conditions]);
        }
    }
}
