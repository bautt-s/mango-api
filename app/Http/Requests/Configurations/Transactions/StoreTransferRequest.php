<?php

namespace App\Http\Requests\Configurations\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_cents' => 'required|integer|min:1',
            'occurred_at' => 'required|date',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'source_account_id' => 'required|uuid|exists:accounts,id',
            'target_account_id' => 'required|uuid|exists:accounts,id|different:source_account_id',
        ];
    }

    public function messages(): array
    {
        return [
            'amount_cents.required' => 'El monto es obligatorio',
            'amount_cents.integer' => 'El monto debe ser un número entero',
            'amount_cents.min' => 'El monto debe ser mayor a 0',

            'occurred_at.required' => 'La fecha de la transacción es obligatoria',
            'occurred_at.date' => 'La fecha debe ser una fecha válida',

            'description.string' => 'La descripción debe ser texto',
            'description.max' => 'La descripción no puede exceder 500 caracteres',

            'notes.string' => 'Las notas deben ser texto',
            'notes.max' => 'Las notas no pueden exceder 2000 caracteres',

            'tags.array' => 'Las etiquetas deben ser un arreglo',
            'tags.max' => 'No puedes agregar más de 10 etiquetas',
            'tags.*.string' => 'Cada etiqueta debe ser texto',
            'tags.*.max' => 'Cada etiqueta no puede exceder 50 caracteres',

            'source_account_id.required' => 'La cuenta de origen es obligatoria',
            'source_account_id.uuid' => 'El ID de cuenta de origen debe ser un UUID válido',
            'source_account_id.exists' => 'La cuenta de origen no existe',

            'target_account_id.required' => 'La cuenta de destino es obligatoria',
            'target_account_id.uuid' => 'El ID de cuenta de destino debe ser un UUID válido',
            'target_account_id.exists' => 'La cuenta de destino no existe',
            'target_account_id.different' => 'Las cuentas de origen y destino deben ser diferentes',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_cents' => 'monto',
            'occurred_at' => 'fecha',
            'description' => 'descripción',
            'notes' => 'notas',
            'tags' => 'etiquetas',
            'source_account_id' => 'cuenta de origen',
            'target_account_id' => 'cuenta de destino',
        ];
    }
}
