<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configurations\Categories\StoreCategoryRequest;
use App\Http\Requests\Configurations\Categories\UpdateCategoryRequest;
use App\Http\Resources\Configurations\CategoryResource;
use App\Models\Configurations\Category;
use App\Services\Configurations\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * Listar todas las categorías disponibles para el usuario
     * (sistema + propias del usuario)
     *
     * GET /api/v1/categories
     * Query params:
     *   - kind: expense|income|both (opcional)
     *   - tree: true|false (devolver en formato árbol jerárquico)
     *   - include_stats: true|false (incluir estadísticas de uso)
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            $user = $request->user();
            $kind = $request->query('kind');
            $asTree = $request->boolean('tree', false);
            $includeStats = $request->boolean('include_stats', false);

            // Obtener categorías
            $categories = $this->categoryService->getCategoriesForUser($user, $kind);

            // Si se solicita formato árbol
            if ($asTree) {
                $tree = $this->categoryService->buildCategoryTree($categories);
                return $this->successResponse($tree, 'Árbol de categorías obtenido exitosamente');
            }

            // Si se solicitan estadísticas, agregar stats a cada categoría
            if ($includeStats) {
                $categories->each(function ($category) {
                    $category->stats = $this->categoryService->getCategoryStats($category);
                });
            }

            return CategoryResource::collection($categories);
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Crear una nueva categoría personalizada
     *
     * POST /api/v1/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $category = $this->categoryService->createCategory($user, $validated);
            $category->load(['parent', 'defaultAccount', 'children']);
            
            return $this->successResponse(
                new CategoryResource($category),
                'Categoría creada exitosamente',
                201
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Actualizar una categoría existente
     *
     * PUT /api/v1/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            // Verificar autorización
            if (!$this->canModifyCategory($request->user(), $category)) {
                return $this->unauthorizedResponse('No tienes permiso para modificar esta categoría');
            }

            $validated = $request->validated();
            $category = $this->categoryService->updateCategory($category, $validated);
            $category->load(['parent', 'defaultAccount', 'children']);

            return $this->successResponse(
                new CategoryResource($category),
                'Categoría actualizada exitosamente'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Eliminar una categoría
     *
     * DELETE /api/v1/categories/{category}
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        try {
            // Verificar autorización
            if (!$this->canModifyCategory($request->user(), $category)) {
                return $this->unauthorizedResponse('No tienes permiso para eliminar esta categoría');
            }

            // Verificar si es categoría del sistema
            if ($category->isSystem()) {
                return $this->errorResponse(
                    'No se pueden eliminar categorías del sistema',
                    403
                );
            }

            // Obtener estadísticas antes de eliminar
            $stats = $this->categoryService->getCategoryStats($category);

            $this->categoryService->deleteCategory($category);

            return $this->successResponse(
                [
                    'deleted' => true,
                    'affected_transactions' => $stats['transaction_count'],
                    'affected_children' => $stats['children_count'],
                ],
                'Categoría eliminada exitosamente'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Obtener estadísticas de uso de una categoría
     *
     * GET /api/v1/categories/{category}/stats
     */
    public function stats(Request $request, Category $category): JsonResponse
    {
        try {
            // Verificar que el usuario tenga acceso a esta categoría
            if (!$this->canAccessCategory($request->user(), $category)) {
                return $this->unauthorizedResponse('No tienes permiso para ver esta categoría');
            }

            $stats = $this->categoryService->getCategoryStats($category);

            return $this->successResponse(
                $stats,
                'Estadísticas de categoría obtenidas exitosamente'
            );
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Obtener solo las categorías raíz (sin padre)
     *
     * GET /api/v1/categories/roots
     */
    public function roots(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            $user = $request->user();
            $kind = $request->query('kind');

            $categories = $this->categoryService->getRootCategories($user, $kind);

            return CategoryResource::collection($categories);
        } catch (Throwable $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Verificar si el usuario puede modificar la categoría
     */
    private function canModifyCategory($user, Category $category): bool
    {
        // Solo puede modificar categorías propias (no del sistema)
        return !$category->isSystem() && $category->belongsToUser($user->id);
    }

    /**
     * Verificar si el usuario puede acceder a la categoría
     */
    private function canAccessCategory($user, Category $category): bool
    {
        // Puede acceder si es del sistema o es propia
        return $category->isSystem() || $category->belongsToUser($user->id);
    }
}
