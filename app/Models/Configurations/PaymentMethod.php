<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'issuer',
        'network',
        'last4',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCreditCards($query)
    {
        return $query->where('type', 'credit_card');
    }

    public function scopeDebitCards($query)
    {
        return $query->where('type', 'debit_card');
    }

    // Helper methods
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isCreditCard(): bool
    {
        return $this->type === 'credit_card';
    }

    public function isDebitCard(): bool
    {
        return $this->type === 'debit_card';
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    public function getDisplayName(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->last4) {
            return ($this->network ?? ucfirst($this->type)) . ' ****' . $this->last4;
        }

        return ucfirst(str_replace('_', ' ', $this->type));
    }

    public function makeDefault(): bool
    {
        // Remove default from other payment methods
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        return $this->save();
    }
}
