<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configurations\Budgets\StoreBudgetRequest;
use App\Http\Requests\Configurations\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Configurations\BudgetResource;
use App\Models\Configurations\Budget;
use App\Services\Configurations\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BudgetController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Obtener todos los presupuestos del usuario
     * GET /api/v1/budgets
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->query('period'); // 'monthly' o 'yearly'
            
            $budgets = $this->budgetService->getBudgetsForUser($user, $period);

            return $this->successResponse(
                BudgetResource::collection($budgets),
                'Presupuestos obtenidos exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Crear un nuevo presupuesto
     * POST /api/v1/budgets
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $budget = $this->budgetService->createBudget($user, $request->validated());

            return $this->successResponse(
                new BudgetResource($budget),
                'Presupuesto creado exitosamente.',
                201
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Mostrar un presupuesto específico
     * GET /api/v1/budgets/{budget}
     */
    public function show(Request $request, Budget $budget): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar ownership
            if (!$budget->user_id === $user->id) {
                return $this->errorResponse(
                    'No tienes permiso para ver este presupuesto.',
                    403
                );
            }

            $budget->load('category');

            return $this->successResponse(
                new BudgetResource($budget),
                'Presupuesto obtenido exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Actualizar un presupuesto
     * PUT /api/v1/budgets/{budget}
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar ownership
            if ($budget->user_id !== $user->id) {
                return $this->errorResponse(
                    'No tienes permiso para modificar este presupuesto.',
                    403
                );
            }

            $budget = $this->budgetService->updateBudget($budget, $request->validated());

            return $this->successResponse(
                new BudgetResource($budget),
                'Presupuesto actualizado exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Eliminar un presupuesto
     * DELETE /api/v1/budgets/{budget}
     */
    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar ownership
            if ($budget->user_id !== $user->id) {
                return $this->errorResponse(
                    'No tienes permiso para eliminar este presupuesto.',
                    403
                );
            }

            $this->budgetService->deleteBudget($budget);

            return $this->successResponse(
                null,
                'Presupuesto eliminado exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Obtener presupuestos del período actual con cálculos
     * GET /api/v1/budgets/current
     */
    public function current(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $budgets = $this->budgetService->getCurrentPeriodBudgets($user);

            // Agregar cálculos a cada presupuesto
            $budgetsWithCalculations = $budgets->map(function ($budget) {
                $spent = $this->budgetService->calculateSpent($budget);
                $remaining = $this->budgetService->calculateRemaining($budget);
                $percentageUsed = $budget->limit_cents > 0 
                    ? round(($spent / $budget->limit_cents) * 100, 2) 
                    : 0;

                return [
                    'budget' => new BudgetResource($budget),
                    'spent_cents' => $spent,
                    'spent' => $spent / 100,
                    'remaining_cents' => $remaining,
                    'remaining' => $remaining / 100,
                    'percentage_used' => $percentageUsed,
                    'is_over_budget' => $spent > $budget->limit_cents,
                    'alert_triggered' => $this->budgetService->checkThreshold($budget),
                ];
            });

            return $this->successResponse(
                $budgetsWithCalculations,
                'Presupuestos del período actual obtenidos exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Activar/desactivar rollover para un presupuesto
     * PATCH /api/v1/budgets/{budget}/rollover
     */
    public function toggleRollover(Request $request, Budget $budget): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar ownership
            if ($budget->user_id !== $user->id) {
                return $this->errorResponse(
                    'No tienes permiso para modificar este presupuesto.',
                    403
                );
            }

            $budget = $this->budgetService->toggleRollover($budget);

            return $this->successResponse(
                new BudgetResource($budget),
                'Rollover actualizado exitosamente.'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }
}