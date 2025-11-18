<?php

namespace App\Http\Requests\Configurations\Budgets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'period' => ['sometimes', 'required', Rule::in(['monthly', 'yearly'])],
            'limit_cents' => 'sometimes|required|integer|min:1',
            'currency_code' => 'sometimes|required|string|size:3',
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                          ->orWhere('is_system', true);
                }),
            ],
            'enable_rollover' => 'sometimes|boolean',
            'alert_threshold' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del presupuesto es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'period.required' => 'El período es obligatorio.',
            'period.in' => 'El período debe ser "monthly" o "yearly".',
            'limit_cents.required' => 'El límite es obligatorio.',
            'limit_cents.integer' => 'El límite debe ser un número entero.',
            'limit_cents.min' => 'El límite debe ser al menos 1 centavo.',
            'currency_code.required' => 'El código de moneda es obligatorio.',
            'currency_code.size' => 'El código de moneda debe tener 3 caracteres.',
            'category_id.uuid' => 'El ID de categoría debe ser un UUID válido.',
            'category_id.exists' => 'La categoría seleccionada no existe o no te pertenece.',
            'enable_rollover.boolean' => 'El rollover debe ser verdadero o falso.',
            'alert_threshold.integer' => 'El umbral de alerta debe ser un número entero.',
            'alert_threshold.min' => 'El umbral de alerta debe ser al menos 1%.',
            'alert_threshold.max' => 'El umbral de alerta no puede superar el 100%.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que category_id sea null si viene vacío
        if ($this->has('category_id') && empty($this->category_id)) {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'period' => 'período',
            'limit_cents' => 'límite',
            'currency_code' => 'código de moneda',
            'category_id' => 'categoría',
            'enable_rollover' => 'rollover',
            'alert_threshold' => 'umbral de alerta',
        ];
    }
}