<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'color' => $this->color,
            'currency_code' => $this->currency_code,
            'is_default' => $this->is_default,
            'archived' => $this->archived,
            'sort_order' => $this->sort_order,
            'balance' => $this->getBalance(),
            'balance_formatted' => $this->getBalanceFormatted(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
