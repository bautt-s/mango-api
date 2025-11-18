<?php

namespace App\Services\Configurations;

use App\Models\Configurations\Budget;
use App\Models\Configurations\Transaction;
use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetService
{
    /**
     * Obtener todos los presupuestos del usuario
     */
    public function getBudgetsForUser(User $user, ?string $period = null): Collection
    {
        $query = Budget::where('user_id', $user->id)
            ->with('category');

        if ($period) {
            $query->where('period', $period);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Crear un nuevo presupuesto
     */
    public function createBudget(User $user, array $data): Budget
    {
        DB::beginTransaction();

        try {
            // Verificar si ya existe un presupuesto para esta categoría y período
            $existingBudget = Budget::where('user_id', $user->id)
                ->where('category_id', $data['category_id'] ?? null)
                ->where('period', $data['period'])
                ->first();

            if ($existingBudget) {
                throw new \Exception('Ya existe un presupuesto para esta categoría y período.');
            }

            // Preparar metadata
            $metadata = [
                'enable_rollover' => $data['enable_rollover'] ?? false,
                'alert_threshold' => $data['alert_threshold'] ?? null,
            ];

            $budget = Budget::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'period' => $data['period'],
                'limit_cents' => $data['limit_cents'],
                'currency_code' => $data['currency_code'],
                'category_id' => $data['category_id'] ?? null,
                'metadata' => $metadata,
            ]);

            Log::channel('audit')->info('Budget created', [
                'budget_id' => $budget->id,
                'user_id' => $user->id,
                'name' => $budget->name,
                'period' => $budget->period,
                'limit_cents' => $budget->limit_cents,
            ]);

            DB::commit();

            return $budget->fresh(['category']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating budget', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar un presupuesto existente
     */
    public function updateBudget(Budget $budget, array $data): Budget
    {
        DB::beginTransaction();

        try {
            // Si se está cambiando la categoría o período, verificar duplicados
            if (isset($data['category_id']) || isset($data['period'])) {
                $newCategoryId = $data['category_id'] ?? $budget->category_id;
                $newPeriod = $data['period'] ?? $budget->period;

                $existingBudget = Budget::where('user_id', $budget->user_id)
                    ->where('id', '!=', $budget->id)
                    ->where('category_id', $newCategoryId)
                    ->where('period', $newPeriod)
                    ->first();

                if ($existingBudget) {
                    throw new \Exception('Ya existe otro presupuesto para esta categoría y período.');
                }
            }

            // Actualizar campos básicos
            $budget->fill([
                'name' => $data['name'] ?? $budget->name,
                'period' => $data['period'] ?? $budget->period,
                'limit_cents' => $data['limit_cents'] ?? $budget->limit_cents,
                'currency_code' => $data['currency_code'] ?? $budget->currency_code,
                'category_id' => $data['category_id'] ?? $budget->category_id,
            ]);

            // Actualizar metadata si se proporcionan
            $metadata = $budget->metadata ?? [];
            if (isset($data['enable_rollover'])) {
                $metadata['enable_rollover'] = $data['enable_rollover'];
            }
            if (isset($data['alert_threshold'])) {
                $metadata['alert_threshold'] = $data['alert_threshold'];
            }
            $budget->metadata = $metadata;

            $budget->save();

            Log::channel('audit')->info('Budget updated', [
                'budget_id' => $budget->id,
                'user_id' => $budget->user_id,
                'changes' => $budget->getChanges(),
            ]);

            DB::commit();

            return $budget->fresh(['category']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating budget', [
                'error' => $e->getMessage(),
                'budget_id' => $budget->id,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar un presupuesto
     */
    public function deleteBudget(Budget $budget): bool
    {
        try {
            $budgetId = $budget->id;
            $userId = $budget->user_id;

            $budget->delete();

            Log::channel('audit')->info('Budget deleted', [
                'budget_id' => $budgetId,
                'user_id' => $userId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Error deleting budget', [
                'error' => $e->getMessage(),
                'budget_id' => $budget->id,
            ]);
            throw $e;
        }
    }

    /**
     * Obtener presupuestos del período actual
     */
    public function getCurrentPeriodBudgets(User $user): Collection
    {
        return Budget::where('user_id', $user->id)
            ->with('category')
            ->get();
    }

    /**
     * Calcular el monto gastado para un presupuesto
     */
    public function calculateSpent(Budget $budget): int
    {
        [$startDate, $endDate] = $this->getPeriodDates($budget->period);

        $query = Transaction::where('user_id', $budget->user_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (!$budget->isGlobal()) {
            $query->where('category_id', $budget->category_id);
        }

        return (int) $query->sum('amount_cents');
    }

    /**
     * Calcular el monto restante
     */
    public function calculateRemaining(Budget $budget): int
    {
        $spent = $this->calculateSpent($budget);
        $remaining = $budget->limit_cents - $spent;

        // Si rollover está habilitado, sumar el saldo del período anterior
        if ($budget->metadata['enable_rollover'] ?? false) {
            $rollover = $this->calculateRollover($budget);
            $remaining += $rollover;
        }

        return max(0, $remaining);
    }

    /**
     * Calcular rollover del período anterior
     */
    public function calculateRollover(Budget $budget): int
    {
        if (!($budget->metadata['enable_rollover'] ?? false)) {
            return 0;
        }

        [$previousStart, $previousEnd] = $this->getPreviousPeriodDates($budget->period);

        $spent = Transaction::where('user_id', $budget->user_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$previousStart, $previousEnd])
            ->when(!$budget->isGlobal(), function ($query) use ($budget) {
                $query->where('category_id', $budget->category_id);
            })
            ->sum('amount_cents');

        $remaining = $budget->limit_cents - $spent;

        return $remaining > 0 ? (int) $remaining : 0;
    }

    /**
     * Verificar si se alcanzó el umbral de alerta
     */
    public function checkThreshold(Budget $budget): bool
    {
        $threshold = $budget->metadata['alert_threshold'] ?? null;

        if (!$threshold) {
            return false;
        }

        $percentageUsed = ($this->calculateSpent($budget) / $budget->limit_cents) * 100;

        return $percentageUsed >= $threshold;
    }

    /**
     * Toggle rollover para un presupuesto
     */
    public function toggleRollover(Budget $budget): Budget
    {
        DB::beginTransaction();

        try {
            $metadata = $budget->metadata ?? [];
            $currentRollover = $metadata['enable_rollover'] ?? false;
            $metadata['enable_rollover'] = !$currentRollover;

            $budget->metadata = $metadata;
            $budget->save();

            Log::channel('audit')->info('Budget rollover toggled', [
                'budget_id' => $budget->id,
                'user_id' => $budget->user_id,
                'enable_rollover' => $metadata['enable_rollover'],
            ]);

            DB::commit();

            return $budget->fresh(['category']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error toggling rollover', [
                'error' => $e->getMessage(),
                'budget_id' => $budget->id,
            ]);
            throw $e;
        }
    }

    /**
     * Obtener fechas del período actual
     */
    private function getPeriodDates(string $period): array
    {
        if ($period === 'monthly') {
            return [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ];
        }

        // yearly
        return [
            now()->startOfYear(),
            now()->endOfYear(),
        ];
    }

    /**
     * Obtener fechas del período anterior
     */
    private function getPreviousPeriodDates(string $period): array
    {
        if ($period === 'monthly') {
            return [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ];
        }

        // yearly
        return [
            now()->subYear()->startOfYear(),
            now()->subYear()->endOfYear(),
        ];
    }
}
