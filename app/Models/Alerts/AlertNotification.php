<?php

namespace App\Models\Alerts;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'alert_id',
        'user_id',
        'channel',
        'status',
        'message_body',
        'delivery_metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'delivery_metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // ==================== Relationships ====================

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Scopes ====================

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered', 'read']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeUnread($query)
    {
        return $query->where('channel', 'in_app')
            ->whereNull('read_at');
    }

    // ==================== Helper Methods ====================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRead(): bool
    {
        return $this->status === 'read' && $this->read_at !== null;
    }

    public function markAsSent(): bool
    {
        $this->status = 'sent';
        $this->sent_at = now();
        return $this->save();
    }

    public function markAsDelivered(): bool
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        return $this->save();
    }

    public function markAsRead(): bool
    {
        $this->status = 'read';
        $this->read_at = now();
        return $this->save();
    }

    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = 'failed';
        $this->failed_at = now();
        $this->error_message = $errorMessage;
        return $this->save();
    }

    public function retry(): bool
    {
        if (!$this->isFailed()) {
            return false;
        }

        $this->status = 'pending';
        $this->failed_at = null;
        $this->error_message = null;
        return $this->save();
    }

    // ==================== Static Methods ====================

    public static function createPending(Alert $alert, string $channel, string $messageBody): self
    {
        return self::create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'channel' => $channel,
            'status' => 'pending',
            'message_body' => $messageBody,
        ]);
    }
}