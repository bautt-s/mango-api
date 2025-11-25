<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'sent_at' => 'datetime',
        'transactions_count' => 'integer',
        'total_expense_cents' => 'integer',
        'total_income_cents' => 'integer',
    ];

    // ===== Relationships =====

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===== Scopes =====

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('summary_date', $date);
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('sent_at');
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('summary_date', '>=', now()->subDays($days)->toDateString());
    }

    // ===== Helper Methods =====

    public function isSent(): bool
    {
        return !is_null($this->sent_at);
    }

    public function isPending(): bool
    {
        return is_null($this->sent_at);
    }

    public function markAsSent(?string $templateName = null): void
    {
        $this->update([
            'sent_at' => now(),
            'template_name' => $templateName ?? $this->template_name,
        ]);
    }

    public function getTotalExpense(): float
    {
        return $this->total_expense_cents / 100;
    }

    public function getTotalIncome(): float
    {
        return $this->total_income_cents / 100;
    }

    public function getNetAmount(): float
    {
        return ($this->total_income_cents - $this->total_expense_cents) / 100;
    }

    public function getNetAmountCents(): int
    {
        return $this->total_income_cents - $this->total_expense_cents;
    }

    public function hasActivity(): bool
    {
        return $this->transactions_count > 0;
    }

    public function getFormattedExpense(): string
    {
        return number_format($this->getTotalExpense(), 2) . ' ' . $this->currency_code;
    }

    public function getFormattedIncome(): string
    {
        return number_format($this->getTotalIncome(), 2) . ' ' . $this->currency_code;
    }

    public function getFormattedNet(): string
    {
        $net = $this->getNetAmount();
        $sign = $net >= 0 ? '+' : '';
        return $sign . number_format($net, 2) . ' ' . $this->currency_code;
    }

    // ===== Static Helpers =====

    public static function summaryExists(User $user, string $date, string $channel): bool
    {
        return static::where('user_id', $user->id)
            ->where('summary_date', $date)
            ->where('channel', $channel)
            ->exists();
    }

    public static function getOrCreate(User $user, string $date, string $channel): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $user->id,
                'summary_date' => $date,
                'channel' => $channel,
            ],
            [
                'transactions_count' => 0,
                'total_expense_cents' => 0,
                'total_income_cents' => 0,
                'currency_code' => $user->currency_code,
            ]
        );
    }
}