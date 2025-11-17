<?php

namespace App\Http\Requests\Configurations\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;

class SetBillingCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle_day' => 'required|integer|min:1|max:31',
            'due_day' => 'required|integer|min:1|max:31',
            'credit_limit_cents' => 'nullable|integer|min:0',
            'alert_days_before' => 'nullable|integer|min:0|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'billing_cycle_day.required' => 'El día del ciclo de facturación es obligatorio',
            'billing_cycle_day.integer' => 'El día del ciclo de facturación debe ser un número entero',
            'billing_cycle_day.min' => 'El día del ciclo de facturación debe ser al menos 1',
            'billing_cycle_day.max' => 'El día del ciclo de facturación no puede exceder 31',

            'due_day.required' => 'El día de vencimiento es obligatorio',
            'due_day.integer' => 'El día de vencimiento debe ser un número entero',
            'due_day.min' => 'El día de vencimiento debe ser al menos 1',
            'due_day.max' => 'El día de vencimiento no puede exceder 31',

            'credit_limit_cents.integer' => 'El límite de crédito debe ser un número entero',
            'credit_limit_cents.min' => 'El límite de crédito debe ser mayor o igual a 0',

            'alert_days_before.integer' => 'Los días de alerta deben ser un número entero',
            'alert_days_before.min' => 'Los días de alerta deben ser al menos 0',
            'alert_days_before.max' => 'Los días de alerta no pueden exceder 30',
        ];
    }

    public function attributes(): array
    {
        return [
            'billing_cycle_day' => 'día del ciclo de facturación',
            'due_day' => 'día de vencimiento',
            'credit_limit_cents' => 'límite de crédito',
            'alert_days_before' => 'días de alerta',
        ];
    }
}