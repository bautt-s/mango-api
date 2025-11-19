<?php

namespace App\Models\Alerts;

use App\Models\Configurations\Account;
use App\Models\Configurations\Budget;
use App\Models\Configurations\PaymentMethod;
use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'description',
        'conditions',
        'metadata',
        'active',
        'last_triggered_at',
        'snoozed_until',
        'frequency',
        'trigger_count',
    ];

    protected $casts = [
        'conditions' => 'array',
        'metadata' => 'array',
        'active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'trigger_count' => 'integer',
    ];

    // ==================== Relationships ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class);
    }

    // Budget relationship (if applicable)
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'conditions->budget_id');
    }

    // Account relationship (if applicable)
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'conditions->account_id');
    }

    // Payment method relationship (if applicable)
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'conditions->payment_method_id');
    }

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeNotSnoozed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('snoozed_until')
                ->orWhere('snoozed_until', '<=', now());
        });
    }

    public function scopeReadyToTrigger($query)
    {
        return $query->active()
            ->notSnoozed()
            ->where(function ($q) {
                $q->whereNull('last_triggered_at')
                    ->orWhereRaw('
                              (frequency = "daily" AND last_triggered_at < NOW() - INTERVAL 1 DAY) OR
                              (frequency = "weekly" AND last_triggered_at < NOW() - INTERVAL 7 DAY) OR
                              (frequency = "monthly" AND last_triggered_at < NOW() - INTERVAL 1 MONTH) OR
                              (frequency = "every_time")
                          ');
            });
    }

    // ==================== Helper Methods ====================

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until && $this->snoozed_until->isFuture();
    }

    public function canTrigger(): bool
    {
        if (!$this->isActive() || $this->isSnoozed()) {
            return false;
        }

        // Check frequency
        if ($this->frequency === 'once' && $this->last_triggered_at) {
            return false;
        }

        if ($this->frequency === 'daily' && $this->last_triggered_at?->isToday()) {
            return false;
        }

        if ($this->frequency === 'weekly' && $this->last_triggered_at?->isCurrentWeek()) {
            return false;
        }

        if ($this->frequency === 'monthly' && $this->last_triggered_at?->isCurrentMonth()) {
            return false;
        }

        return true;
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function snooze(int $hours = 24): bool
    {
        $this->snoozed_until = now()->addHours($hours);
        return $this->save();
    }

    public function unsnooze(): bool
    {
        $this->snoozed_until = null;
        return $this->save();
    }

    public function activate(): bool
    {
        $this->active = true;
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->active = false;
        return $this->save();
    }

    public function markTriggered(): bool
    {
        $this->last_triggered_at = now();
        $this->trigger_count++;
        return $this->save();
    }

    public function getConditionValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->conditions, $key, $default);
    }

    public function isBudgetAlert(): bool
    {
        return in_array($this->type, ['budget_threshold', 'budget_exceeded']);
    }

    public function isPaymentAlert(): bool
    {
        return $this->type === 'payment_due';
    }

    public function isBalanceAlert(): bool
    {
        return $this->type === 'low_balance';
    }

    // ==================== Static Methods ====================

    public static function availableTypes(): array
    {
        return [
            'budget_threshold' => 'Umbral de presupuesto alcanzado',
            'budget_exceeded' => 'Presupuesto excedido',
            'payment_due' => 'Vencimiento de pago próximo',
            'low_balance' => 'Saldo bajo en cuenta',
            'milestone_reached' => 'Hito financiero alcanzado',
            'unusual_spending' => 'Gasto inusual detectado',
            'recurring_transaction_missed' => 'Transacción recurrente no registrada',
        ];
    }

    public static function availableFrequencies(): array
    {
        return [
            'once' => 'Una vez',
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            'every_time' => 'Cada vez',
        ];
    }
}