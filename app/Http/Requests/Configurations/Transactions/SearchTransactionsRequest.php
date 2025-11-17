<?php

namespace App\Http\Requests\Configurations\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class SearchTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'type' => 'nullable|in:expense,income,transfer',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'min_amount_cents' => 'nullable|integer|min:0',
            'max_amount_cents' => 'nullable|integer|min:0',
            'search_term' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'La fecha desde debe ser una fecha válida',

            'date_to.date' => 'La fecha hasta debe ser una fecha válida',
            'date_to.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde',

            'type.in' => 'El tipo debe ser: gasto, ingreso o transferencia',

            'account_id.uuid' => 'El ID de cuenta debe ser un UUID válido',
            'account_id.exists' => 'La cuenta no existe',

            'category_id.uuid' => 'El ID de categoría debe ser un UUID válido',
            'category_id.exists' => 'La categoría no existe',

            'payment_method_id.uuid' => 'El ID de método de pago debe ser un UUID válido',
            'payment_method_id.exists' => 'El método de pago no existe',

            'min_amount_cents.integer' => 'El monto mínimo debe ser un número entero',
            'min_amount_cents.min' => 'El monto mínimo debe ser mayor o igual a 0',

            'max_amount_cents.integer' => 'El monto máximo debe ser un número entero',
            'max_amount_cents.min' => 'El monto máximo debe ser mayor o igual a 0',

            'search_term.string' => 'El término de búsqueda debe ser texto',
            'search_term.max' => 'El término de búsqueda no puede exceder 255 caracteres',

            'is_recurring.boolean' => 'El campo recurrente debe ser verdadero o falso',

            'per_page.integer' => 'Los elementos por página deben ser un número entero',
            'per_page.min' => 'Debe haber al menos 1 elemento por página',
            'per_page.max' => 'No puedes mostrar más de 100 elementos por página',
        ];
    }

    public function attributes(): array
    {
        return [
            'date_from' => 'fecha desde',
            'date_to' => 'fecha hasta',
            'type' => 'tipo',
            'account_id' => 'cuenta',
            'category_id' => 'categoría',
            'payment_method_id' => 'método de pago',
            'min_amount_cents' => 'monto mínimo',
            'max_amount_cents' => 'monto máximo',
            'search_term' => 'término de búsqueda',
            'is_recurring' => 'recurrente',
            'per_page' => 'elementos por página',
        ];
    }
}
