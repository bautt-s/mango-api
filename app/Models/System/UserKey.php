<?php

namespace App\Models\System;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKey extends Model
{
    use HasUuids;

    protected $table = 'user_keys';

    protected $fillable = [
        'user_id',
        'dek_wrapped',
        'version',
        'rotated_at',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this encryption key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wrapped DEK as a base64 string.
     */
    public function getWrappedDekBase64(): string
    {
        return base64_encode($this->dek_wrapped);
    }

    /**
     * Set the wrapped DEK from a base64 string.
     */
    public function setWrappedDekFromBase64(string $base64): void
    {
        $this->dek_wrapped = base64_decode($base64);
    }

    /**
     * Check if this key needs rotation based on age.
     */
    public function needsRotation(int $daysThreshold = 90): bool
    {
        if (!$this->rotated_at) {
            return $this->created_at->diffInDays(now()) > $daysThreshold;
        }

        return $this->rotated_at->diffInDays(now()) > $daysThreshold;
    }

    /**
     * Mark this key as rotated.
     */
    public function markAsRotated(): bool
    {
        return $this->update(['rotated_at' => now()]);
    }
}
