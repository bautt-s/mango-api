<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'user_id',
        'category_id',
        'limit_cents', 
        'currency_code',
        'period',
    ];

    protected $casts = [
        'limit_cents' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scopes
    public function scopeMonthly($query)
    {
        return $query->where('period', 'monthly');
    }

    public function scopeYearly($query)
    {
        return $query->where('period', 'yearly');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('category_id');
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Accessors
    public function getLimitAttribute(): float
    {
        return $this->limit_cents / 100;
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return $this->category_id === null;
    }

    public function getSpentAmount(): int
    {
        $startDate = $this->period === 'monthly'
            ? now()->startOfMonth()
            : now()->startOfYear();

        $endDate = $this->period === 'monthly'
            ? now()->endOfMonth()
            : now()->endOfYear();

        $query = Transaction::where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (!$this->isGlobal()) {
            $query->where('category_id', $this->category_id);
        }

        return $query->sum('amount_cents');
    }

    public function getRemainingAmount(): int
    {
        return max(0, $this->limit_cents - $this->getSpentAmount());
    }

    public function getPercentageUsed(): float
    {
        if ($this->limit_cents == 0) {
            return 0;
        }

        return min(100, ($this->getSpentAmount() / $this->limit_cents) * 100);
    }

    public function isOverBudget(): bool
    {
        return $this->getSpentAmount() > $this->limit_cents;
    }
}