<?php

declare(strict_types=1);

namespace App\Http\Resources\Personal;

use App\Http\Resources\Subscriptions\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'locale' => $this->locale,
            'role' => $this->role,
            'is_premium' => $this->is_premium,
            'premium_since' => $this->premium_since?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'subscription' => $this->whenLoaded('subscription', function () {
                return new SubscriptionResource($this->subscription);
            }),
        ];
    }
}