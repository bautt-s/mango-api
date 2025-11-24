<?php

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SubscriptionResource
 * 
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_preapproval_id' => $this->provider_preapproval_id,

            // Información del plan
            'plan' => [
                'id' => $this->plan->id,
                'code' => $this->plan->code,
                'name' => $this->plan->name,
                'interval' => $this->plan->interval,
                'price' => $this->plan->price,
                'price_cents' => $this->plan->price_cents,
                'currency_code' => $this->plan->currency_code,
            ],

            // Estados booleanos
            'is_active' => in_array($this->status, ['active', 'trialing']),
            'is_trialing' => $this->status === 'trialing',
            'is_canceled' => $this->status === 'canceled',
            'is_past_due' => $this->status === 'past_due',
            'can_resume' => $this->status === 'canceled'
                && $this->ends_at
                && $this->ends_at->isFuture(),

            // Fechas
            'started_at' => $this->started_at?->toDateTimeString(),
            'renews_at' => $this->renews_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'canceled_at' => $this->canceled_at?->toDateTimeString(),

            // Información adicional
            'days_remaining' => $this->renews_at
                ? now()->diffInDays($this->renews_at)
                : null,
            'next_billing_amount' => $this->status === 'active'
                ? $this->plan->price
                : null,

            // Timestamps
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}