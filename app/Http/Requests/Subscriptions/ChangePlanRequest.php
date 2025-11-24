<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ChangePlanRequest
 * 
 */
class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El usuario debe tener una suscripción activa
        $user = $this->user();

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->exists();

        return $hasActiveSubscription;
    }

    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                'string',
                'exists:plans,code',
                'different:current_plan', // No puede ser el mismo plan
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_code.required' => 'El código del plan es obligatorio',
            'plan_code.exists' => 'El plan seleccionado no existe',
            'plan_code.different' => 'Ya estás suscrito a este plan',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Agregar el plan actual para validación
        $user = $this->user();
        $currentSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->with('plan')
            ->first();

        if ($currentSubscription) {
            $this->merge([
                'current_plan' => $currentSubscription->plan->code,
            ]);
        }
    }
}