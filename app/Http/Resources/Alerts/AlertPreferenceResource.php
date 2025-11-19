<?php

namespace App\Http\Resources\Alerts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email_enabled' => $this->email_enabled,
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'in_app_enabled' => $this->in_app_enabled,
            'type_preferences' => $this->type_preferences,
            'quiet_hours' => $this->quiet_hours,
            'active_days' => $this->active_days,
            'active_days_labels' => $this->getActiveDaysLabels(),
            'enable_digest' => $this->enable_digest,
            'digest_frequency' => $this->digest_frequency,
            'digest_time' => $this->digest_time?->format('H:i'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    private function getActiveDaysLabels(): ?array
    {
        if (!$this->active_days) {
            return null;
        }

        $dayLabels = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];

        return array_map(fn($day) => $dayLabels[$day] ?? $day, $this->active_days);
    }
}