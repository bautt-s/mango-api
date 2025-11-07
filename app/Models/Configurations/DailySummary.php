<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'summary_date',
        'transactions_count',
        'total_expense_cents',
        'total_income_cents',
        'currency_code',
        'channel',
        'template_name',
        'sent_at',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'transactions_count' => 'integer',
        'total_expense_cents' => 'integer',
        'total_income_cents' => 'integer',
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('sent_at');
    }

    public function scopeWhatsapp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('summary_date', $date);
    }

    // Accessors
    public function getTotalExpenseAttribute(): float
    {
        return $this->total_expense_cents / 100;
    }

    public function getTotalIncomeAttribute(): float
    {
        return $this->total_income_cents / 100;
    }

    public function getNetBalanceAttribute(): float
    {
        return ($this->total_income_cents - $this->total_expense_cents) / 100;
    }

    // Helper methods
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function markAsSent(): bool
    {
        $this->sent_at = now();
        return $this->save();
    }
}
