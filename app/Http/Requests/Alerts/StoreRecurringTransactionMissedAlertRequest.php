<?php

namespace App\Http\Requests\Alerts;

use App\Models\Configurations\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionMissedAlertRequest extends FormRequest
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
            'conditions.recurrence_group_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    // Verify that the recurrence group exists and belongs to the user
                    $count = Transaction::where('user_id', $this->user()->id)
                        ->where('recurrence_group_id', $value)
                        ->count();
                    
                    if ($count === 0) {
                        $fail('El grupo de recurrencia especificado no existe o no te pertenece.');
                    } elseif ($count < 2) {
                        $fail('El grupo de recurrencia debe tener al menos 2 transacciones para establecer un patrón.');
                    }
                },
            ],
            'conditions.tolerance_days' => 'nullable|integer|min:0|max:14',
            'active' => 'boolean',
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
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
            
            'conditions.recurrence_group_id.required' => 'El ID del grupo de recurrencia es obligatorio.',
            'conditions.recurrence_group_id.uuid' => 'El ID del grupo de recurrencia debe ser un UUID válido.',
            
            'conditions.tolerance_days.integer' => 'Los días de tolerancia deben ser un número entero.',
            'conditions.tolerance_days.min' => 'Los días de tolerancia no pueden ser negativos.',
            'conditions.tolerance_days.max' => 'Los días de tolerancia no pueden exceder 14.',
            
            'active.boolean' => 'El estado activo debe ser verdadero o falso.',
            
            'frequency.required' => 'La frecuencia es obligatoria.',
            'frequency.in' => 'La frecuencia debe ser "daily", "weekly" o "monthly".',
            
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
            'conditions.recurrence_group_id' => 'grupo de recurrencia',
            'conditions.tolerance_days' => 'días de tolerancia',
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
            'type' => 'recurring_transaction_missed',
        ]);

        // Set default tolerance_days if not provided
        if ($this->has('conditions')) {
            $conditions = $this->input('conditions', []);
            
            if (!isset($conditions['tolerance_days'])) {
                $conditions['tolerance_days'] = 2;
            }
            
            $this->merge(['conditions' => $conditions]);
        }
    }
}