<?php

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'interval' => $this->interval,
            'active' => $this->active,

            // Precios
            'price' => $this->price,
            'price_cents' => $this->price_cents,
            'currency_code' => $this->currency_code,
            'price_formatted' => $this->formatPrice(),

            // Información del intervalo
            'interval_label' => $this->getIntervalLabel(),
            'interval_count' => $this->getIntervalCount(),

            // Features (si están cargadas)
            'features' => $this->when(
                $this->relationLoaded('features'),
                function () {
                    return $this->features->map(function ($feature) {
                        return [
                            'slug' => $feature->slug,
                            'kind' => $feature->kind,
                            'enabled' => $feature->pivot->enabled,
                            'quota' => $feature->pivot->quota_override ?? $feature->default_quota,
                            'description' => $feature->description,
                        ];
                    });
                }
            ),

            // Timestamps
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Formatear precio con símbolo de moneda
     */
    protected function formatPrice(): string
    {
        $symbol = match ($this->currency_code) {
            'ARS' => '$',
            'USD' => 'US$',
            'EUR' => '€',
            default => $this->currency_code . ' ',
        };

        return $symbol . number_format($this->price, 2);
    }

    /**
     * Obtener etiqueta legible del intervalo
     */
    protected function getIntervalLabel(): string
    {
        return match ($this->interval) {
            'monthly' => 'Mensual',
            'annual' => 'Anual',
            default => ucfirst($this->interval),
        };
    }

    /**
     * Obtener cantidad de intervalos (para cálculos)
     */
    protected function getIntervalCount(): int
    {
        return match ($this->interval) {
            'monthly' => 1,
            'annual' => 12,
            default => 1,
        };
    }
}