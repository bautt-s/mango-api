<?php

namespace App\Models\Features;

use App\Models\Subscriptions\Plan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'kind',
        'default_quota',
        'description',
    ];

    protected $casts = [
        'default_quota' => 'integer',
    ];

    // Relationships
    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['enabled', 'quota_override'])
            ->withTimestamps();
    }

    public function usage()
    {
        return $this->hasMany(FeatureUsage::class);
    }

    // Scopes
    public function scopeBinary($query)
    {
        return $query->where('kind', 'binary');
    }

    public function scopeQuota($query)
    {
        return $query->where('kind', 'quota');
    }

    // Helper methods
    public function isBinary(): bool
    {
        return $this->kind === 'binary';
    }

    public function isQuota(): bool
    {
        return $this->kind === 'quota';
    }
}
