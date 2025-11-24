<?php

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SubscriptionPaymentResource
 * 
 */
class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'status' => $this->status,

            // Montos
            'amount' => $this->amount_cents / 100,
            'amount_cents' => $this->amount_cents,
            'currency_code' => $this->currency_code,
            'amount_formatted' => $this->formatAmount(),

            // Estados
            'is_paid' => $this->status === 'paid',
            'is_pending' => $this->status === 'pending',
            'is_failed' => $this->status === 'failed',
            'is_refunded' => $this->status === 'refunded',

            // Metadata
            'raw_payload' => $this->when(
                $request->user()?->isAdmin(),
                $this->raw_payload
            ),

            // Timestamps
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Formatear monto con símbolo de moneda
     */
    protected function formatAmount(): string
    {
        $symbol = match ($this->currency_code) {
            'ARS' => '$',
            'USD' => 'US$',
            'EUR' => '€',
            default => $this->currency_code . ' ',
        };

        $amount = $this->amount_cents / 100;

        return $symbol . number_format($amount, 2);
    }
}
