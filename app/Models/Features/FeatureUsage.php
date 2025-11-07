<?php

namespace App\Models\Features;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'feature_usage';

    protected $fillable = [
        'user_id',
        'feature_id',
        'period_ym',
        'used',
    ];

    protected $casts = [
        'used' => 'integer',
    ];

    public $incrementing = false;

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    // Scopes
    public function scopeForPeriod($query, string $periodYm)
    {
        return $query->where('period_ym', $periodYm);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->where('period_ym', now()->format('Y-m'));
    }

    // Helper methods
    public static function getCurrentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public function incrementUsage(int $amount = 1): bool
    {
        $this->used += $amount;
        return $this->save();
    }

    public function hasReachedLimit(int $limit): bool
    {
        return $this->used >= $limit;
    }

    public function getRemainingQuota(int $limit): int
    {
        return max(0, $limit - $this->used);
    }
}
