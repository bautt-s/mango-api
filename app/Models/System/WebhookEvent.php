<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'event_type',
        'external_id',
        'payload',
        'status',
        'attempts',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    // Scopes
    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeMercadoPago($query)
    {
        return $query->where('source', 'mercadopago');
    }

    public function scopeWhatsApp($query)
    {
        return $query->where('source', 'whatsapp');
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopePendingRetry($query, int $maxAttempts = 3)
    {
        return $query->where('status', 'failed')
            ->where('attempts', '<', $maxAttempts);
    }

    // Helper methods
    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessed(): bool
    {
        $this->status = 'processed';
        $this->processed_at = now();
        return $this->save();
    }

    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->attempts++;
        return $this->save();
    }

    public function incrementAttempts(): bool
    {
        $this->attempts++;
        return $this->save();
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->isFailed() && $this->attempts < $maxAttempts;
    }
}