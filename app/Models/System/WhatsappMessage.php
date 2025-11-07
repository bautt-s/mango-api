<?php

namespace App\Models\System;

use App\Casts\EncryptedText;
use App\Models\Configurations\Transaction;
use App\Models\Personal\User;
use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'wa_message_id',
        'direction',
        'status',
        'message_type',
        'body_enc', 
        'body_bi',
        'raw_payload',
        'parsed_json',
        'parse_status',
        'related_transaction_id',
        'received_at',
        'sent_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'parsed_json' => 'array',
        'received_at' => 'datetime',
        'sent_at' => 'datetime',
        'body_enc' => EncryptedText::class, 
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function relatedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }

    // Accessor for encrypted body
    public function getBodyAttribute()
    {
        return $this->body_enc;
    }

    // Convenience accessors
    public function setBodyAttribute($v){ $this->attributes['body_enc'] = $v; }
    
    // Scopes
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

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

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeParseSuccess($query)
    {
        return $query->where('parse_status', 'success');
    }

    public function scopeParseFailed($query)
    {
        return $query->where('parse_status', 'failed');
    }

    public function scopeUnparsed($query)
    {
        return $query->whereNull('parse_status')
            ->orWhere('parse_status', 'skipped');
    }

    public function scopeSearchBody($query, string $searchTerm)
    {
        $bi = BlindIndex::hash($searchTerm);
        return $query->where('body_bi', $bi);
    }

    // Helper methods
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function wasParseSuccessful(): bool
    {
        return $this->parse_status === 'success';
    }

    public function hasParseFailed(): bool
    {
        return $this->parse_status === 'failed';
    }

    public function markAsProcessed(): bool
    {
        $this->status = 'processed';
        return $this->save();
    }

    public function markAsFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }

    public function markParseSuccess(array $parsedData): bool
    {
        $this->parse_status = 'success';
        $this->parsed_json = $parsedData;
        return $this->save();
    }

    public function markParseFailed(): bool
    {
        $this->parse_status = 'failed';
        return $this->save();
    }
}