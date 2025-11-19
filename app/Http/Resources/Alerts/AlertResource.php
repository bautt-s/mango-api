<?php

namespace App\Http\Resources\Alerts;

use App\Http\Resources\Configurations\AccountResource;
use App\Http\Resources\Configurations\BudgetResource;
use App\Http\Resources\Configurations\PaymentMethodResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'name' => $this->name,
            'description' => $this->description,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
            'active' => $this->active,
            'last_triggered_at' => $this->last_triggered_at?->toIso8601String(),
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
            'is_snoozed' => $this->isSnoozed(),
            'can_trigger' => $this->canTrigger(),
            'frequency' => $this->frequency,
            'frequency_label' => $this->getFrequencyLabel(),
            'trigger_count' => $this->trigger_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Include recent notifications if loaded
            'recent_notifications' => AlertNotificationResource::collection($this->whenLoaded('notifications')),
            
            // Include related entities if needed
            'budget' => new BudgetResource($this->whenLoaded('budget')),
            'account' => new AccountResource($this->whenLoaded('account')),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
        ];
    }

    private function getTypeLabel(): string
    {
        $types = [
            'budget_threshold' => 'Umbral de presupuesto',
            'budget_exceeded' => 'Presupuesto excedido',
            'payment_due' => 'Vencimiento de pago',
            'low_balance' => 'Saldo bajo',
            'milestone_reached' => 'Hito alcanzado',
            'unusual_spending' => 'Gasto inusual',
            'recurring_transaction_missed' => 'Transacción recurrente perdida',
        ];

        return $types[$this->type] ?? $this->type;
    }

    private function getFrequencyLabel(): string
    {
        $frequencies = [
            'once' => 'Una vez',
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            'every_time' => 'Cada vez',
        ];

        return $frequencies[$this->frequency] ?? $this->frequency;
    }
}