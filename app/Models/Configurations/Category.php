<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'kind',
        'color',
        'icon',
        'is_system',
        'parent_id',
        'default_account_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function defaultAccount()
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    // Scopes
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeUserOwned($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeExpense($query)
    {
        return $query->whereIn('kind', ['expense', 'both']);
    }

    public function scopeIncome($query)
    {
        return $query->whereIn('kind', ['income', 'both']);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('is_system', true);
        });
    }

    // Helper methods
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function isExpense(): bool
    {
        return in_array($this->kind, ['expense', 'both']);
    }

    public function isIncome(): bool
    {
        return in_array($this->kind, ['income', 'both']);
    }

    public function hasParent(): bool
    {
        return $this->parent_id !== null;
    }
}
