<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),

            // Amount information
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => $this->getFormattedAmount(),
            'currency_code' => $this->currency_code,

            // Date
            'occurred_at' => $this->occurred_at?->toISOString(),

            // Encrypted fields (automatically decrypted by casts)
            'description' => $this->description,
            'merchant' => $this->merchant,
            'notes' => $this->notes,
            'tags' => $this->tags,

            // Relationships - conditional loading
            'account' => $this->when(
                $this->relationLoaded('account') && $this->account,
                function () {
                    return new AccountResource($this->account);
                }
            ),

            'source_account' => $this->when(
                $this->relationLoaded('sourceAccount') && $this->sourceAccount,
                function () {
                    return new AccountResource($this->sourceAccount);
                }
            ),

            'target_account' => $this->when(
                $this->relationLoaded('targetAccount') && $this->targetAccount,
                function () {
                    return new AccountResource($this->targetAccount);
                }
            ),

            'category' => $this->when(
                $this->relationLoaded('category') && $this->category,
                function () {
                    return new CategoryResource($this->category);
                }
            ),

            'payment_method' => $this->when(
                $this->relationLoaded('paymentMethod') && $this->paymentMethod,
                function () {
                    return new PaymentMethodResource($this->paymentMethod);
                }
            ),

            // Recurrence information
            'is_recurring' => $this->is_recurring,
            'recurrence_group_id' => $this->recurrence_group_id,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * Get human-readable type label
     */
    protected function getTypeLabel(): string
    {
        return match ($this->type) {
            'expense' => 'Gasto',
            'income' => 'Ingreso',
            'transfer' => 'Transferencia',
            default => 'Desconocido',
        };
    }

    public function getFormattedAmount(): string
    {
        $amount = $this->amount_cents / 100;
        $prefix = $this->type === 'income' ? '+' : '-';

        if ($this->type === 'transfer') {
            $prefix = '';
        }

        return $prefix . number_format($amount, 2) . ' ' . $this->currency_code;
    }
}