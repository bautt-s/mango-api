<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billingCycle = $this->metadata['billing_cycle'] ?? null;
        $currentPeriodSpending = null;

        // Calculate current period spending if billing cycle exists
        if ($billingCycle && $this->isCreditCard()) {
            $service = app(\App\Services\Configurations\PaymentMethodService::class);
            $currentPeriodSpending = $service->getCurrentPeriodSpending($this->resource);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'display_name' => $this->getDisplayName(),
            'issuer' => $this->issuer,
            'network' => $this->network,
            'last4' => $this->last4,
            'is_default' => $this->is_default,

            // Billing cycle information
            'billing_cycle' => $billingCycle ? [
                'billing_cycle_day' => $billingCycle['billing_cycle_day'],
                'due_day' => $billingCycle['due_day'],
                'credit_limit_cents' => $billingCycle['credit_limit_cents'] ?? null,
                'alert_days_before' => $billingCycle['alert_days_before'] ?? null,
                'next_due_date' => $this->getNextDueDateFormatted(),
            ] : null,

            // Current period spending (only for credit cards with billing cycle)
            'current_period_spending_cents' => $currentPeriodSpending,

            'metadata' => $this->metadata,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get formatted next due date
     */
    protected function getNextDueDateFormatted(): ?string
    {
        if (!$this->metadata || !isset($this->metadata['billing_cycle'])) {
            return null;
        }

        $service = app(\App\Services\Configurations\PaymentMethodService::class);
        $nextDue = $service->getNextDueDate($this->resource);

        return $nextDue?->toISOString();
    }
}