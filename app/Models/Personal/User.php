<?php

namespace App\Models\Personal;

use App\Models\Configurations\Account;
use App\Models\Configurations\Budget;
use App\Models\Configurations\Category;
use App\Models\Configurations\DailySummary;
use App\Models\Configurations\PaymentMethod;
use App\Models\Configurations\Transaction;
use App\Models\Features\FeatureUsage;
use App\Models\Subscriptions\Subscription;
use App\Models\System\WhatsappMessage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'timezone',
        'currency_code',
        'locale',
        'role',
        'is_premium',
        'premium_since',
        'trial_ends_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'premium_since' => 'datetime',
        'trial_ends_at' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // Relationships
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->orWhere('status', 'trialing');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function dailySummaries()
    {
        return $this->hasMany(DailySummary::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function featureUsage()
    {
        return $this->hasMany(FeatureUsage::class);
    }

    // Scopes
    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPremium(): bool
    {
        return $this->is_premium;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasFeature(string $featureSlug): bool
    {
        if (!$this->isPremium()) {
            return false;
        }

        $subscription = $this->activeSubscription;
        if (!$subscription) {
            return false;
        }

        return $subscription->plan->features()
            ->where('slug', $featureSlug)
            ->wherePivot('enabled', true)
            ->exists();
    }
}