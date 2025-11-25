<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Configurations\DailySummaryResource;
use App\Models\Configurations\DailySummary;
use App\Services\Configurations\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailySummaryController extends Controller
{
    protected DailySummaryService $summaryService;

    public function __construct(DailySummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    /**
     * Listar resúmenes diarios del usuario
     * GET /api/v1/summaries
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Parámetros de filtro
            $startDate = $request->query('start_date')
                ? Carbon::parse($request->query('start_date'))
                : now()->subDays(30);

            $endDate = $request->query('end_date')
                ? Carbon::parse($request->query('end_date'))
                : now();

            $channel = $request->query('channel');

            $summaries = $this->summaryService->getSummaries($user, $startDate, $endDate, $channel);

            return $this->successResponse(
                DailySummaryResource::collection($summaries),
                'Resúmenes diarios obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Generar resumen para una fecha específica
     * POST /api/v1/summaries/generate
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'date' => 'nullable|date|before_or_equal:today',
                'channel' => 'nullable|in:whatsapp,email',
            ], [
                'date.date' => 'La fecha debe ser una fecha válida.',
                'date.before_or_equal' => 'La fecha no puede ser futura.',
                'channel.in' => 'El canal debe ser whatsapp o email.',
            ]);

            $date = isset($validated['date'])
                ? Carbon::parse($validated['date'])
                : now()->subDay();

            $channel = $validated['channel'] ?? 'whatsapp';

            $summary = $this->summaryService->generateDailySummary($user, $date, $channel);

            return $this->successResponse(
                new DailySummaryResource($summary),
                'Resumen diario generado exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Obtener estadísticas detalladas de un día
     * GET /api/v1/summaries/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'date' => 'nullable|date',
            ], [
                'date.date' => 'La fecha debe ser una fecha válida.',
            ]);

            $date = isset($validated['date'])
                ? Carbon::parse($validated['date'])
                : now()->subDay();

            $detailedStats = $this->summaryService->generateDetailedStats($user, $date);

            return $this->successResponse(
                $detailedStats,
                'Estadísticas detalladas obtenidas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Obtener resumen de un día específico
     * GET /api/v1/summaries/{date}
     */
    public function show(Request $request, string $date): JsonResponse
    {
        try {
            $user = $request->user();

            // Validar formato de fecha
            try {
                $parsedDate = Carbon::parse($date);
            } catch (\Exception $e) {
                return $this->errorResponse(
                    null,
                    'Formato de fecha inválido.',
                    400
                );
            }

            $channel = $request->query('channel', 'whatsapp');

            // Buscar resumen existente
            $summary = DailySummary::forUser($user)
                ->forDate($parsedDate->toDateString())
                ->byChannel($channel)
                ->first();

            if (!$summary) {
                return $this->errorResponse(
                    null,
                    'No se encontró resumen para esta fecha.',
                    404
                );
            }

            return $this->successResponse(
                new DailySummaryResource($summary),
                'Resumen diario obtenido exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Obtener vista previa del mensaje que se enviará
     * GET /api/v1/summaries/preview
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'date' => 'nullable|date',
                'channel' => 'nullable|in:whatsapp,email',
            ], [
                'date.date' => 'La fecha debe ser una fecha válida.',
                'channel.in' => 'El canal debe ser whatsapp o email.',
            ]);

            $date = isset($validated['date'])
                ? Carbon::parse($validated['date'])
                : now()->subDay();

            $channel = $validated['channel'] ?? 'whatsapp';

            // Generar o buscar resumen
            $summary = $this->summaryService->generateDailySummary($user, $date, $channel);

            // Obtener estadísticas detalladas
            $detailedStats = $this->summaryService->generateDetailedStats($user, $date);

            // Generar mensaje
            $message = $this->summaryService->generateSummaryMessage($summary, $detailedStats);

            return $this->successResponse([
                'summary' => new DailySummaryResource($summary),
                'message' => $message,
                'detailed_stats' => $detailedStats,
            ], 'Vista previa generada exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Obtener resumen de la semana actual
     * GET /api/v1/summaries/week
     */
    public function week(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $summaries = $this->summaryService->getSummaries($user, $startOfWeek, $endOfWeek);

            // Calcular totales de la semana
            $weekTotals = [
                'total_transactions' => $summaries->sum('transactions_count'),
                'total_expense_cents' => $summaries->sum('total_expense_cents'),
                'total_income_cents' => $summaries->sum('total_income_cents'),
                'days_with_activity' => $summaries->filter->hasActivity()->count(),
                'currency_code' => $user->currency_code,
            ];

            return $this->successResponse([
                'week_range' => [
                    'start' => $startOfWeek->toDateString(),
                    'end' => $endOfWeek->toDateString(),
                ],
                'totals' => $weekTotals,
                'daily_summaries' => DailySummaryResource::collection($summaries),
            ], 'Resumen semanal obtenido exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Obtener resumen del mes actual
     * GET /api/v1/summaries/month
     */
    public function month(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $summaries = $this->summaryService->getSummaries($user, $startOfMonth, $endOfMonth);

            // Calcular totales del mes
            $monthTotals = [
                'total_transactions' => $summaries->sum('transactions_count'),
                'total_expense_cents' => $summaries->sum('total_expense_cents'),
                'total_income_cents' => $summaries->sum('total_income_cents'),
                'days_with_activity' => $summaries->filter->hasActivity()->count(),
                'average_daily_expense_cents' => $summaries->count() > 0
                    ? round($summaries->sum('total_expense_cents') / $summaries->count())
                    : 0,
                'currency_code' => $user->currency_code,
            ];

            return $this->successResponse([
                'month_range' => [
                    'start' => $startOfMonth->toDateString(),
                    'end' => $endOfMonth->toDateString(),
                ],
                'totals' => $monthTotals,
                'daily_summaries' => DailySummaryResource::collection($summaries),
            ], 'Resumen mensual obtenido exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}