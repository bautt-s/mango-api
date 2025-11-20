<?php

namespace App\Http\Resources\Personal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->getCategory(),
            'is_achieved' => $this->isAchieved(),
            'reached_at' => $this->reached_at?->toIso8601String(),
            'time_ago' => $this->reached_at ? $this->reached_at->diffForHumans() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Get milestone category
     */
    private function getCategory(): string
    {
        $code = $this->code;

        if (str_starts_with($code, 'transaction') || $code === 'first_transaction') {
            return 'transactions';
        }
        if (str_starts_with($code, 'daily_streak')) {
            return 'streaks';
        }
        if (str_starts_with($code, 'category')) {
            return 'categories';
        }
        if (str_starts_with($code, 'budget')) {
            return 'budgets';
        }
        if ($code === 'whatsapp_first') {
            return 'whatsapp';
        }
        return 'special';
    }
}