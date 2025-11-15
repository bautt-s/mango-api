<?php

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->plan);
            }),
            'provider' => $this->provider,
            'provider_preapproval_id' => $this->provider_preapproval_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'renews_at' => $this->renews_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}