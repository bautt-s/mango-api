<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
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
            'name' => $this->name,
            'period' => $this->period,
            'limit_cents' => $this->limit_cents,
            'limit' => $this->limit_cents / 100,
            'currency_code' => $this->currency_code,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'is_global' => $this->isGlobal(),

            // Metadata (stored in JSON if needed)
            'enable_rollover' => $this->metadata['enable_rollover'] ?? false,
            'alert_threshold' => $this->metadata['alert_threshold'] ?? null,

            // Calculated fields
            'spent_cents' => $this->when(
                $request->get('include_calculations', false),
                fn() => $this->getSpentAmount()
            ),
            'spent' => $this->when(
                $request->get('include_calculations', false),
                fn() => $this->getSpentAmount() / 100
            ),
            'remaining_cents' => $this->when(
                $request->get('include_calculations', false),
                fn() => $this->getRemainingAmount()
            ),
            'remaining' => $this->when(
                $request->get('include_calculations', false),
                fn() => $this->getRemainingAmount() / 100
            ),
            'percentage_used' => $this->when(
                $request->get('include_calculations', false),
                fn() => round($this->getPercentageUsed(), 2)
            ),
            'is_over_budget' => $this->when(
                $request->get('include_calculations', false),
                fn() => $this->isOverBudget()
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
