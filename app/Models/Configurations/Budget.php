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
        'metadata',
    ];

    protected $casts = [
        'limit_cents' => 'integer',
        'metadata' => 'array',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * Relación con User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con Category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // ===== SCOPES =====

    /**
     * Filtrar presupuestos mensuales
     */
    public function scopeMonthly($query)
    {
        return $query->where('period', 'monthly');
    }

    /**
     * Filtrar presupuestos anuales
     */
    public function scopeYearly($query)
    {
        return $query->where('period', 'yearly');
    }

    /**
     * Filtrar presupuestos globales (sin categoría)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('category_id');
    }

    /**
     * Filtrar por categoría específica
     */
    public function scopeForCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Filtrar por usuario
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ===== ACCESSORS =====

    /**
     * Obtener el límite en formato decimal
     */
    public function getLimitAttribute(): float
    {
        return $this->limit_cents / 100;
    }

    // ===== HELPER METHODS =====

    /**
     * Verificar si el presupuesto es global
     */
    public function isGlobal(): bool
    {
        return $this->category_id === null;
    }

    /**
     * Verificar si pertenece a un usuario
     */
    public function belongsToUser(string $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Calcular el monto gastado en el período actual
     */
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

        return (int) $query->sum('amount_cents');
    }

    /**
     * Calcular el monto restante
     */
    public function getRemainingAmount(): int
    {
        return max(0, $this->limit_cents - $this->getSpentAmount());
    }

    /**
     * Calcular el porcentaje usado
     */
    public function getPercentageUsed(): float
    {
        if ($this->limit_cents == 0) {
            return 0;
        }

        return min(100, ($this->getSpentAmount() / $this->limit_cents) * 100);
    }

    /**
     * Verificar si se excedió el presupuesto
     */
    public function isOverBudget(): bool
    {
        return $this->getSpentAmount() > $this->limit_cents;
    }

    /**
     * Verificar si el rollover está habilitado
     */
    public function hasRolloverEnabled(): bool
    {
        return $this->metadata['enable_rollover'] ?? false;
    }

    /**
     * Obtener el umbral de alerta
     */
    public function getAlertThreshold(): ?int
    {
        return $this->metadata['alert_threshold'] ?? null;
    }
}