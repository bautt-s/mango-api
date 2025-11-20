<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Personal\MilestoneResource;
use App\Models\Personal\Milestone;
use App\Services\Personal\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    protected MilestoneService $milestoneService;

    public function __construct(MilestoneService $milestoneService)
    {
        $this->milestoneService = $milestoneService;
    }

    /**
     * Get all milestones for authenticated user
     * GET /api/v1/milestones
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get filter (achieved, not_achieved, or all)
            $filter = $request->query('filter', 'all');

            $query = Milestone::where('user_id', $user->id)
                ->orderBy('reached_at', 'desc')
                ->orderBy('created_at', 'desc');

            if ($filter === 'achieved') {
                $query->achieved();
            } elseif ($filter === 'not_achieved') {
                $query->notAchieved();
            }

            $milestones = $query->get();

            return $this->successResponse(
                MilestoneResource::collection($milestones),
                'Hitos obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get milestone progress overview
     * GET /api/v1/milestones/progress
     */
    public function progress(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $progress = $this->milestoneService->getMilestoneProgress($user);

            // Group by category
            $grouped = collect($progress)->groupBy('category');

            return $this->successResponse([
                'summary' => [
                    'total_milestones' => count($progress),
                    'achieved' => collect($progress)->where('is_achieved', true)->count(),
                    'not_achieved' => collect($progress)->where('is_achieved', false)->count(),
                    'completion_percentage' => count($progress) > 0
                        ? round((collect($progress)->where('is_achieved', true)->count() / count($progress)) * 100, 2)
                        : 0,
                ],
                'by_category' => $grouped->map(function ($items, $category) {
                    return [
                        'category' => $category,
                        'total' => $items->count(),
                        'achieved' => $items->where('is_achieved', true)->count(),
                        'milestones' => $items->values(),
                    ];
                }),
                'all_milestones' => $progress,
            ], 'Progreso de hitos obtenido exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get recent achievements
     * GET /api/v1/milestones/recent
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $days = $request->query('days', 30);

            $milestones = Milestone::where('user_id', $user->id)
                ->recentlyAchieved($days)
                ->orderBy('reached_at', 'desc')
                ->get();

            return $this->successResponse(
                MilestoneResource::collection($milestones),
                'Hitos recientes obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Check milestones and return newly achieved
     * POST /api/v1/milestones/check
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $newlyAchieved = $this->milestoneService->checkMilestones($user);

            if ($newlyAchieved->isEmpty()) {
                return $this->successResponse(
                    [],
                    'No hay nuevos hitos alcanzados.'
                );
            }

            return $this->successResponse(
                MilestoneResource::collection($newlyAchieved),
                '¡Felicitaciones! Has alcanzado ' . $newlyAchieved->count() . ' nuevo(s) hito(s).'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get statistics about achievements
     * GET /api/v1/milestones/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $totalMilestones = Milestone::where('user_id', $user->id)->count();
            $achievedMilestones = Milestone::where('user_id', $user->id)->achieved()->count();
            $recentAchievements = Milestone::where('user_id', $user->id)
                ->recentlyAchieved(7)
                ->count();

            $firstAchievement = Milestone::where('user_id', $user->id)
                ->achieved()
                ->orderBy('reached_at', 'asc')
                ->first();

            $latestAchievement = Milestone::where('user_id', $user->id)
                ->achieved()
                ->orderBy('reached_at', 'desc')
                ->first();

            return $this->successResponse([
                'total_milestones' => $totalMilestones,
                'achieved_milestones' => $achievedMilestones,
                'pending_milestones' => $totalMilestones - $achievedMilestones,
                'completion_percentage' => $totalMilestones > 0
                    ? round(($achievedMilestones / $totalMilestones) * 100, 2)
                    : 0,
                'recent_achievements_7d' => $recentAchievements,
                'first_achievement' => $firstAchievement
                    ? new MilestoneResource($firstAchievement)
                    : null,
                'latest_achievement' => $latestAchievement
                    ? new MilestoneResource($latestAchievement)
                    : null,
            ], 'Estadísticas de hitos obtenidas exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get a specific milestone
     * GET /api/v1/milestones/{milestone}
     */
    public function show(Request $request, Milestone $milestone): JsonResponse
    {
        try {
            // Check ownership
            if ($milestone->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            return $this->successResponse(
                new MilestoneResource($milestone),
                'Hito obtenido exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}