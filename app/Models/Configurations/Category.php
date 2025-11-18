<?php

namespace App\Models\Configurations;

use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'categories';

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_system' => false,
        'kind' => 'expense',
    ];

    /**
     * Relación con el usuario propietario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con la categoría padre
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación con las categorías hijas
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relación con la cuenta predeterminada
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    /**
     * Relación con las transacciones
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope para obtener solo categorías del sistema
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true)->whereNull('user_id');
    }

    /**
     * Scope para obtener solo categorías de usuario
     */
    public function scopeUserOwned($query, string $userId)
    {
        return $query->where('user_id', $userId)->where('is_system', false);
    }

    /**
     * Scope para obtener categorías disponibles para un usuario
     * (sistema + propias)
     */
    public function scopeAvailableFor($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_system', true)
                ->whereNull('user_id')
                ->orWhere('user_id', $userId);
        });
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    /**
     * Scope para categorías raíz (sin padre)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope para obtener solo categorías de gastos
     */
    public function scopeExpense($query)
    {
        return $query->where('kind', 'expense');
    }

    /**
     * Scope para obtener solo categorías de ingresos
     */
    public function scopeIncome($query)
    {
        return $query->where('kind', 'income');
    }

    /**
     * Verifica si la categoría es del sistema
     */
    public function isSystem(): bool
    {
        return $this->is_system === true;
    }

    /**
     * Verifica si la categoría pertenece a un usuario específico
     */
    public function belongsToUser(string $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Verifica si es una categoría raíz
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Verifica si tiene categorías hijas
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Obtiene todos los ancestros (padres recursivos)
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Obtiene todos los descendientes (hijos recursivos)
     */
    public function descendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->descendants());
        }

        return $descendants;
    }

    /**
     * Verifica si esta categoría es ancestro de otra
     */
    public function isAncestorOf(Category $category): bool
    {
        $current = $category->parent;

        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Verifica si esta categoría es descendiente de otra
     */
    public function isDescendantOf(Category $category): bool
    {
        return $category->isAncestorOf($this);
    }
}
