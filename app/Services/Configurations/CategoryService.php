<?php

namespace App\Services\Configurations;

use App\Models\Configurations\Account;
use App\Models\Configurations\Category;
use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryService
{
    /**
     * Crear una nueva categoría para el usuario
     *
     * @throws InvalidArgumentException
     */
    public function createCategory(User $user, array $data): Category
    {
        // Validar que el parent_id pertenezca al usuario o sea del sistema
        if (isset($data['parent_id'])) {
            $this->validateParentOwnership($user, $data['parent_id']);
        }

        // Validar que default_account_id pertenezca al usuario
        if (isset($data['default_account_id'])) {
            $this->validateAccountOwnership($user, $data['default_account_id']);
        }

        return DB::transaction(function () use ($user, $data) {
            // Si tiene padre y no se especifica default_account_id, heredar del padre
            if (isset($data['parent_id']) && !isset($data['default_account_id'])) {
                $parent = Category::find($data['parent_id']);
                if ($parent && $parent->default_account_id) {
                    $data['default_account_id'] = $parent->default_account_id;
                }
            }

            $data['user_id'] = $user->id;
            $data['is_system'] = false;

            return Category::create($data);
        });
    }

    /**
     * Actualizar una categoría existente
     *
     * @throws InvalidArgumentException
     */
    public function updateCategory(Category $category, array $data): Category
    {
        // No permitir actualizar categorías del sistema
        if ($category->isSystem()) {
            throw new InvalidArgumentException('No se pueden modificar categorías del sistema');
        }

        // Validar parent_id si se está cambiando
        if (isset($data['parent_id'])) {
            $this->validateParentChange($category, $data['parent_id']);
        }

        // Validar default_account_id si se está cambiando
        if (isset($data['default_account_id'])) {
            $this->validateAccountOwnership($category->user, $data['default_account_id']);
        }

        return DB::transaction(function () use ($category, $data) {
            $category->update($data);
            return $category->fresh();
        });
    }

    /**
     * Eliminar una categoría
     *
     * @throws InvalidArgumentException
     */
    public function deleteCategory(Category $category): bool
    {
        // No permitir eliminar categorías del sistema
        if ($category->isSystem()) {
            throw new InvalidArgumentException('No se pueden eliminar categorías del sistema');
        }

        return DB::transaction(function () use ($category) {
            // Las transacciones relacionadas quedarán sin categoría (null)
            // Esto ya está manejado por cascadeOnDelete en la migración

            // Si tiene hijos, también se eliminarán en cascada
            return $category->delete();
        });
    }

    /**
     * Obtener todas las categorías disponibles para un usuario
     * (sistema + propias)
     */
    public function getCategoriesForUser(User $user, ?string $kind = null): Collection
    {
        $query = Category::availableFor($user->id)
            ->with(['parent', 'defaultAccount', 'children']);

        if ($kind) {
            $query->where(function ($q) use ($kind) {
                $q->where('kind', $kind)
                    ->orWhere('kind', 'both');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Construir árbol jerárquico de categorías
     */
    public function buildCategoryTree(Collection $categories): array
    {
        $tree = [];
        $lookup = [];

        // Crear lookup para acceso rápido
        foreach ($categories as $category) {
            $lookup[$category->id] = [
                'category' => $category,
                'children' => [],
            ];
        }

        // Construir árbol
        foreach ($categories as $category) {
            if ($category->parent_id && isset($lookup[$category->parent_id])) {
                $lookup[$category->parent_id]['children'][] = &$lookup[$category->id];
            } else {
                $tree[] = &$lookup[$category->id];
            }
        }

        return $tree;
    }

    /**
     * Obtener categorías raíz (sin padre)
     */
    public function getRootCategories(User $user, ?string $kind = null): Collection
    {
        $query = Category::availableFor($user->id)
            ->roots()
            ->with(['children', 'defaultAccount']);

        if ($kind) {
            $query->where(function ($q) use ($kind) {
                $q->where('kind', $kind)
                    ->orWhere('kind', 'both');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Validar que no existan referencias circulares al cambiar padre
     *
     * @throws InvalidArgumentException
     */
    private function validateParentChange(Category $category, ?string $newParentId): void
    {
        if (!$newParentId) {
            return; // Convertir en raíz es válido
        }

        // No puede ser su propio padre
        if ($newParentId === $category->id) {
            throw new InvalidArgumentException('Una categoría no puede ser su propio padre');
        }

        $newParent = Category::find($newParentId);
        if (!$newParent) {
            throw new InvalidArgumentException('La categoría padre no existe');
        }

        // Validar que el nuevo padre pertenezca al usuario o sea del sistema
        $this->validateParentOwnership($category->user, $newParentId);

        // Verificar que el nuevo padre no sea descendiente de esta categoría
        if ($newParent->isDescendantOf($category)) {
            throw new InvalidArgumentException('No se puede crear una referencia circular');
        }
    }

    /**
     * Validar que el padre pertenezca al usuario o sea del sistema
     *
     * @throws InvalidArgumentException
     */
    private function validateParentOwnership(User $user, string $parentId): void
    {
        $parent = Category::find($parentId);

        if (!$parent) {
            throw new InvalidArgumentException('La categoría padre no existe');
        }

        // El padre debe ser del sistema o pertenecer al usuario
        if (!$parent->isSystem() && !$parent->belongsToUser($user->id)) {
            throw new InvalidArgumentException('La categoría padre no está disponible para este usuario');
        }
    }

    /**
     * Validar que la cuenta pertenezca al usuario
     *
     * @throws InvalidArgumentException
     */
    private function validateAccountOwnership(User $user, string $accountId): void
    {
        $account = Account::find($accountId);

        if (!$account) {
            throw new InvalidArgumentException('La cuenta no existe');
        }

        if ($account->user_id !== $user->id) {
            throw new InvalidArgumentException('La cuenta no pertenece a este usuario');
        }
    }

    /**
     * Obtener estadísticas de uso de una categoría
     */
    public function getCategoryStats(Category $category): array
    {
        $transactionCount = $category->transactions()->count();
        $childrenCount = $category->children()->count();

        return [
            'transaction_count' => $transactionCount,
            'children_count' => $childrenCount,
            'has_transactions' => $transactionCount > 0,
            'has_children' => $childrenCount > 0,
            'can_delete' => !$category->isSystem(),
        ];
    }
}