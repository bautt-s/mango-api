<?php

namespace App\Http\Requests\Alerts;

use App\Models\Configurations\Account;
use App\Models\Configurations\Budget;
use App\Models\Configurations\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    'budget_threshold',
                    'budget_exceeded',
                    'payment_due',
                    'low_balance',
                    'milestone_reached',
                    'unusual_spending',
                    'recurring_transaction_missed'
                ])
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'required|array',
            'conditions.budget_id' => 'required_if:type,budget_threshold,budget_exceeded|uuid|exists:budgets,id',
            'conditions.threshold_percentage' => 'required_if:type,budget_threshold|integer|min:1|max:100',
            'conditions.payment_method_id' => 'required_if:type,payment_due|uuid|exists:payment_methods,id',
            'conditions.days_before' => 'required_if:type,payment_due|integer|min:1|max:30',
            'conditions.account_id' => 'required_if:type,low_balance|uuid|exists:accounts,id',
            'conditions.threshold_cents' => 'required_if:type,low_balance|integer|min:0',
            'metadata' => 'nullable|array',
            'active' => 'boolean',
            'frequency' => [
                'nullable',
                'string',
                Rule::in(['once', 'daily', 'weekly', 'monthly', 'every_time'])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de alerta es obligatorio',
            'type.in' => 'El tipo de alerta seleccionado no es válido',
            'name.required' => 'El nombre de la alerta es obligatorio',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'description.max' => 'La descripción no puede exceder 1000 caracteres',
            'conditions.required' => 'Las condiciones de la alerta son obligatorias',
            'conditions.budget_id.required_if' => 'El ID del presupuesto es requerido para este tipo de alerta',
            'conditions.budget_id.exists' => 'El presupuesto seleccionado no existe',
            'conditions.threshold_percentage.required_if' => 'El porcentaje de umbral es requerido',
            'conditions.threshold_percentage.min' => 'El porcentaje debe ser al menos 1',
            'conditions.threshold_percentage.max' => 'El porcentaje no puede exceder 100',
            'conditions.payment_method_id.required_if' => 'El ID del método de pago es requerido para este tipo de alerta',
            'conditions.payment_method_id.exists' => 'El método de pago seleccionado no existe',
            'conditions.days_before.required_if' => 'Los días de anticipación son requeridos',
            'conditions.days_before.min' => 'Debe ser al menos 1 día de anticipación',
            'conditions.days_before.max' => 'No puede exceder 30 días de anticipación',
            'conditions.account_id.required_if' => 'El ID de la cuenta es requerido para este tipo de alerta',
            'conditions.account_id.exists' => 'La cuenta seleccionada no existe',
            'conditions.threshold_cents.required_if' => 'El umbral de saldo es requerido',
            'conditions.threshold_cents.min' => 'El umbral debe ser un número positivo',
            'frequency.in' => 'La frecuencia seleccionada no es válida',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de alerta',
            'name' => 'nombre',
            'description' => 'descripción',
            'conditions' => 'condiciones',
            'metadata' => 'metadatos',
            'active' => 'activo',
            'frequency' => 'frecuencia',
        ];
    }

    /**
     * Additional validation after basic rules pass
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verify ownership of budget if provided
            if ($this->has('conditions.budget_id')) {
                $budget = Budget::find($this->input('conditions.budget_id'));
                if ($budget && $budget->user_id !== Auth::id()) {
                    $validator->errors()->add('conditions.budget_id', 'El presupuesto no te pertenece');
                }
            }

            // Verify ownership of payment method if provided
            if ($this->has('conditions.payment_method_id')) {
                $paymentMethod = PaymentMethod::find($this->input('conditions.payment_method_id'));
                if ($paymentMethod && $paymentMethod->user_id !== Auth::id()) {
                    $validator->errors()->add('conditions.payment_method_id', 'El método de pago no te pertenece');
                }
            }

            // Verify ownership of account if provided
            if ($this->has('conditions.account_id')) {
                $account = Account::find($this->input('conditions.account_id'));
                if ($account && $account->user_id !== Auth::id()) {
                    $validator->errors()->add('conditions.account_id', 'La cuenta no te pertenece');
                }
            }
        });
    }
}