<?php

namespace App\Http\Requests\Configurations\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_cents' => 'sometimes|integer|min:1',
            'currency_code' => 'sometimes|string|size:3|in:ARS,USD,EUR',
            'occurred_at' => 'sometimes|date',
            'description' => 'nullable|string|max:500',
            'merchant' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'is_recurring' => 'boolean',
            'recurrence_group_id' => 'nullable|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'amount_cents.integer' => 'El monto debe ser un número entero',
            'amount_cents.min' => 'El monto debe ser mayor a 0',

            'currency_code.size' => 'El código de moneda debe tener 3 caracteres',
            'currency_code.in' => 'El código de moneda debe ser ARS, USD o EUR',

            'occurred_at.date' => 'La fecha debe ser una fecha válida',

            'description.string' => 'La descripción debe ser texto',
            'description.max' => 'La descripción no puede exceder 500 caracteres',

            'merchant.string' => 'El comercio debe ser texto',
            'merchant.max' => 'El comercio no puede exceder 255 caracteres',

            'notes.string' => 'Las notas deben ser texto',
            'notes.max' => 'Las notas no pueden exceder 2000 caracteres',

            'tags.array' => 'Las etiquetas deben ser un arreglo',
            'tags.max' => 'No puedes agregar más de 10 etiquetas',
            'tags.*.string' => 'Cada etiqueta debe ser texto',
            'tags.*.max' => 'Cada etiqueta no puede exceder 50 caracteres',

            'category_id.uuid' => 'El ID de categoría debe ser un UUID válido',
            'category_id.exists' => 'La categoría no existe',

            'payment_method_id.uuid' => 'El ID de método de pago debe ser un UUID válido',
            'payment_method_id.exists' => 'El método de pago no existe',

            'is_recurring.boolean' => 'El campo recurrente debe ser verdadero o falso',

            'recurrence_group_id.uuid' => 'El ID de grupo de recurrencia debe ser un UUID válido',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_cents' => 'monto',
            'currency_code' => 'código de moneda',
            'occurred_at' => 'fecha',
            'description' => 'descripción',
            'merchant' => 'comercio',
            'notes' => 'notas',
            'tags' => 'etiquetas',
            'category_id' => 'categoría',
            'payment_method_id' => 'método de pago',
            'is_recurring' => 'recurrente',
            'recurrence_group_id' => 'grupo de recurrencia',
        ];
    }
}
