<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'color',
        'currency_code',
        'is_default',
        'archived',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'archived' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function transfersOut()
    {
        return $this->hasMany(Transaction::class, 'source_account_id')
            ->where('type', 'transfer');
    }

    public function transfersIn()
    {
        return $this->hasMany(Transaction::class, 'target_account_id')
            ->where('type', 'transfer');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'default_account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('archived', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    // Helper methods
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function makeDefault(): bool
    {
        // Remove default from other accounts
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        return $this->save();
    }

    public function archive(): bool
    {
        if ($this->is_default) {
            return false; // Cannot archive default account
        }

        $this->archived = true;
        return $this->save();
    }

    public function unarchive(): bool
    {
        $this->archived = false;
        return $this->save();
    }

    public function getBalance(): int
    {
        $income = $this->transactions()
            ->where('type', 'income')
            ->sum('amount_cents');

        $expenses = $this->transactions()
            ->where('type', 'expense')
            ->sum('amount_cents');

        $transfersIn = $this->transfersIn()->sum('amount_cents');
        $transfersOut = $this->transfersOut()->sum('amount_cents');

        return $income - $expenses + $transfersIn - $transfersOut;
    }

    public function getBalanceFormatted(): string
    {
        $balance = $this->getBalance() / 100;
        return number_format($balance, 2) . ' ' . $this->currency_code;
    }
}
