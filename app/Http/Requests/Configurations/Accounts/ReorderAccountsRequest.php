<?php

namespace App\Http\Requests\Configurations\Accounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|uuid|exists:accounts,id',
            'accounts.*.sort_order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'accounts.required' => 'Se requiere al menos una cuenta para reordenar',
            'accounts.*.id.required' => 'El ID de la cuenta es requerido',
            'accounts.*.id.uuid' => 'El ID de la cuenta debe ser un UUID válido',
            'accounts.*.id.exists' => 'Una o más cuentas no existen',
            'accounts.*.sort_order.required' => 'El orden es requerido para cada cuenta',
            'accounts.*.sort_order.integer' => 'El orden debe ser un número entero',
            'accounts.*.sort_order.min' => 'El orden no puede ser negativo',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $accountIds = collect($this->input('accounts'))->pluck('id');

            // Check all accounts belong to the authenticated user
            $userAccountsCount = \App\Models\Configurations\Account::whereIn('id', $accountIds)
                ->where('user_id', $user->id)
                ->count();

            if ($userAccountsCount !== $accountIds->count()) {
                $validator->errors()->add(
                    'accounts',
                    'Una o más cuentas no pertenecen al usuario autenticado'
                );
            }
        });
    }
}
