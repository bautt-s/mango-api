<?php

namespace App\Http\Requests\Configurations\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:cash,credit_card,debit_card,bank_transfer,digital_wallet,other',
            'label' => 'nullable|string|max:255',
            'issuer' => 'nullable|string|max:255',
            'network' => 'nullable|string|max:255',
            'last4' => 'nullable|string|size:4',
            'is_default' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo de método de pago debe ser: efectivo, tarjeta de crédito, tarjeta de débito, transferencia bancaria, billetera digital u otro',

            'label.string' => 'La etiqueta debe ser texto',
            'label.max' => 'La etiqueta no puede exceder 255 caracteres',

            'issuer.string' => 'El emisor debe ser texto',
            'issuer.max' => 'El emisor no puede exceder 255 caracteres',

            'network.string' => 'La red debe ser texto',
            'network.max' => 'La red no puede exceder 255 caracteres',

            'last4.string' => 'Los últimos 4 dígitos deben ser texto',
            'last4.size' => 'Debe proporcionar exactamente 4 dígitos',

            'is_default.boolean' => 'El campo predeterminado debe ser verdadero o falso',

            'metadata.array' => 'Los metadatos deben ser un objeto JSON válido',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'label' => 'etiqueta',
            'issuer' => 'emisor',
            'network' => 'red',
            'last4' => 'últimos 4 dígitos',
            'is_default' => 'predeterminado',
            'metadata' => 'metadatos',
        ];
    }
}