<?php

namespace App\Models\Configurations;

use App\Models\ExchangeRate\WhatsappMessage;
use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'amount_cents',
        'currency_code',
        'occurred_at',
        'description',
        'merchant',
        'notes',
        'account_id',
        'source_account_id',
        'target_account_id',
        'category_id',
        'payment_method_id',
        'is_recurring',
        'recurrence_group_id',
        'tags',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'occurred_at' => 'datetime',
        'is_recurring' => 'boolean',
        'tags' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function targetAccount()
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function whatsappMessage()
    {
        return $this->hasOne(WhatsappMessage::class, 'related_transaction_id');
    }

    // Scopes
    public function scopeExpenses($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeTransfers($query)
    {
        return $query->where('type', 'transfer');
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('occurred_at', now()->month)
            ->whereYear('occurred_at', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('occurred_at', now()->year);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where(function ($q) use ($accountId) {
            $q->where('account_id', $accountId)
                ->orWhere('source_account_id', $accountId)
                ->orWhere('target_account_id', $accountId);
        });
    }

    // Accessors
    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function getFormattedAmountAttribute(): string
    {
        $amount = $this->amount_cents / 100;
        $prefix = $this->type === 'income' ? '+' : '-';

        if ($this->type === 'transfer') {
            $prefix = '';
        }

        return $prefix . number_format($amount, 2) . ' ' . $this->currency_code;
    }

    // Helper methods
    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function isTransfer(): bool
    {
        return $this->type === 'transfer';
    }

    public function hasTag(string $tag): bool
    {
        return $this->tags && in_array($tag, $this->tags);
    }

    public function addTag(string $tag): bool
    {
        if ($this->hasTag($tag)) {
            return false;
        }

        $tags = $this->tags ?? [];
        $tags[] = $tag;
        $this->tags = $tags;

        return $this->save();
    }

    public function removeTag(string $tag): bool
    {
        if (!$this->hasTag($tag)) {
            return false;
        }

        $tags = $this->tags ?? [];
        $this->tags = array_values(array_filter($tags, fn($t) => $t !== $tag));

        return $this->save();
    }
}
