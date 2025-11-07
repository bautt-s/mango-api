<?php

namespace App\Models\Subscriptions;

use App\Models\Features\Feature;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'interval',
        'price_cents',
        'currency_code',
        'active',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'active' => 'boolean',
    ];

    // Relationships
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['enabled', 'quota_override'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeMonthly($query)
    {
        return $query->where('interval', 'monthly');
    }

    public function scopeAnnual($query)
    {
        return $query->where('interval', 'annual');
    }

    // Accessors
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->active;
    }

    public function hasFeature(string $featureSlug): bool
    {
        return $this->features()
            ->where('slug', $featureSlug)
            ->wherePivot('enabled', true)
            ->exists();
    }

    public function getFeatureQuota(string $featureSlug): ?int
    {
        $feature = $this->features()
            ->where('slug', $featureSlug)
            ->first();

        if (!$feature) {
            return null;
        }

        return $feature->pivot->quota_override ?? $feature->default_quota;
    }
}
