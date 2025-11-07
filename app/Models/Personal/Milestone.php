<?php

namespace App\Models\Personal;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'code', 
        'title',
        'description',
        'reached_at',
    ];

    protected $casts = [
        'reached_at' => 'datetime', 
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeAchieved($query)
    {
        return $query->whereNotNull('reached_at');
    }

    public function scopeNotAchieved($query)
    {
        return $query->whereNull('reached_at');
    }

    public function scopeRecentlyAchieved($query, int $days = 7)
    {
        return $query->whereNotNull('reached_at')
            ->where('reached_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isAchieved(): bool
    {
        return $this->reached_at !== null;
    }

    public function achieve(): bool
    {
        if ($this->isAchieved()) {
            return false;
        }

        $this->reached_at = now();
        return $this->save();
    }

    public function reset(): bool
    {
        $this->reached_at = null;
        return $this->save();
    }
}
