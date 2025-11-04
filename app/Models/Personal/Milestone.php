<?php

namespace App\Models\Personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'title',
        'description',
        'achieved_at',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeAchieved($query)
    {
        return $query->whereNotNull('achieved_at');
    }

    public function scopeNotAchieved($query)
    {
        return $query->whereNull('achieved_at');
    }

    public function scopeRecentlyAchieved($query, int $days = 7)
    {
        return $query->whereNotNull('achieved_at')
            ->where('achieved_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    public function achieve(): bool
    {
        if ($this->isAchieved()) {
            return false;
        }

        $this->achieved_at = now();
        return $this->save();
    }

    public function reset(): bool
    {
        $this->achieved_at = null;
        return $this->save();
    }
}
