<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'period',
        'period_start_date',
        'period_end_date',
        'amount_cents',
        'currency_code',
        'active',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'amount_cents' => 'integer',
        'active' => 'boolean',
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
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('category_id');
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeCurrent($query)
    {
        $now = now();
        return $query->where('period_start_date', '<=', $now)
            ->where('period_end_date', '>=', $now);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('period_start_date', [$startDate, $endDate])
                ->orWhereBetween('period_end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('period_start_date', '<=', $startDate)
                        ->where('period_end_date', '>=', $endDate);
                });
        });
    }

    // Accessors
    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->active;
    }

    public function isGlobal(): bool
    {
        return $this->category_id === null;
    }

    public function isCurrent(): bool
    {
        $now = now();
        return $this->period_start_date <= $now && $this->period_end_date >= $now;
    }

    public function getSpentAmount(): int
    {
        $query = Transaction::where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [
                $this->period_start_date->startOfDay(),
                $this->period_end_date->endOfDay()
            ]);

        if (!$this->isGlobal()) {
            $query->where('category_id', $this->category_id);
        }

        return $query->sum('amount_cents');
    }

    public function getRemainingAmount(): int
    {
        return max(0, $this->amount_cents - $this->getSpentAmount());
    }

    public function getPercentageUsed(): float
    {
        if ($this->amount_cents == 0) {
            return 0;
        }

        return min(100, ($this->getSpentAmount() / $this->amount_cents) * 100);
    }

    public function isOverBudget(): bool
    {
        return $this->getSpentAmount() > $this->amount_cents;
    }
}
