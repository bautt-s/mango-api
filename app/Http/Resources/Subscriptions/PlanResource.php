<?php

declare(strict_types=1);

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'interval' => $this->interval,
            'price_cents' => $this->price_cents,
            'price_formatted' => $this->formatPrice(),
            'currency_code' => $this->currency_code,
            'active' => $this->active,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format the price for display
     */
    private function formatPrice(): string
    {
        $amount = $this->price_cents / 100;
        return number_format($amount, 2, ',', '.') . ' ' . $this->currency_code;
    }
}
