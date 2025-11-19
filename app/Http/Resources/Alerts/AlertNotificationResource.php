<?php

namespace App\Http\Resources\Alerts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_id' => $this->alert_id,
            'channel' => $this->channel,
            'channel_label' => $this->getChannelLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'message_body' => $this->message_body,
            'delivery_metadata' => $this->delivery_metadata,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'is_read' => $this->isRead(),
            'created_at' => $this->created_at->toIso8601String(),

            // Include alert details if loaded
            'alert' => new AlertResource($this->whenLoaded('alert')),
        ];
    }

    private function getChannelLabel(): string
    {
        $channels = [
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'in_app' => 'En la app',
            'push' => 'Push',
        ];

        return $channels[$this->channel] ?? $this->channel;
    }

    private function getStatusLabel(): string
    {
        $statuses = [
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'failed' => 'Fallido',
            'read' => 'Leído',
        ];

        return $statuses[$this->status] ?? $this->status;
    }
}