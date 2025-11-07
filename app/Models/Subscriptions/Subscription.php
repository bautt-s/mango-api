<?php

namespace App\Models\Subscriptions;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'plan_id',
        'provider',
        'provider_preapproval_id',
        'status',
        'started_at',
        'renews_at',
        'ends_at',
        'canceled_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'renews_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    // Helper methods
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function cancel(): bool
    {
        $this->status = 'canceled';
        $this->canceled_at = now();
        return $this->save();
    }

    public function resume(): bool
    {
        if ($this->status !== 'canceled') {
            return false;
        }

        $this->status = 'active';
        $this->canceled_at = null;
        return $this->save();
    }
}
