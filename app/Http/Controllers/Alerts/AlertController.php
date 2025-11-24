<?php

namespace App\Http\Controllers\Alerts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alerts\StoreAlertRequest;
use App\Http\Requests\Alerts\UpdateAlertRequest;
use App\Http\Requests\Alerts\UpdateAlertPreferencesRequest;
use App\Http\Resources\Alerts\AlertNotificationResource;
use App\Http\Resources\Alerts\AlertPreferenceResource;
use App\Http\Resources\Alerts\AlertResource;
use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertNotification;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        private AlertService $alertService
    ) {
    }

    /**
     * Get all alerts for authenticated user
     * GET /api/alerts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $type = $request->query('type');
            $activeOnly = $request->query('active_only') ? (bool) $request->query('active_only') : null;

            $alerts = $this->alertService->getAlertsForUser($user, $type, $activeOnly);

            return $this->successResponse(
                AlertResource::collection($alerts),
                'Alertas obtenidas exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Create a new alert
     * POST /api/alerts
     */
    public function store(StoreAlertRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $alert = $this->alertService->createAlert($user, $validated);

            return $this->successResponse(
                new AlertResource($alert),
                'Alerta creada exitosamente.',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Get a specific alert
     * GET /api/alerts/{alert}
     */
    public function show(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para ver esta alerta.', 403);
            }

            // Load relationships
            $alert->load(['notifications' => fn($q) => $q->latest()->limit(10)]);

            return $this->successResponse(
                new AlertResource($alert),
                'Alerta obtenida exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Update an existing alert
     * PUT /api/alerts/{alert}
     */
    public function update(UpdateAlertRequest $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para actualizar esta alerta.', 403);
            }

            $validated = $request->validated();
            $alert = $this->alertService->updateAlert($alert, $validated);

            return $this->successResponse(
                new AlertResource($alert),
                'Alerta actualizada exitosamente.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Delete an alert
     * DELETE /api/alerts/{alert}
     */
    public function destroy(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para eliminar esta alerta.', 403);
            }

            $this->alertService->deleteAlert($alert);

            return $this->successResponse(
                null,
                'Alerta eliminada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Activate an alert
     * PATCH /api/alerts/{alert}/activate
     */
    public function activate(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para activar esta alerta.', 403);
            }

            $alert->activate();

            return $this->successResponse(
                new AlertResource($alert->fresh()),
                'Alerta activada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Deactivate an alert
     * PATCH /api/alerts/{alert}/deactivate
     */
    public function deactivate(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para desactivar esta alerta.', 403);
            }

            $alert->deactivate();

            return $this->successResponse(
                new AlertResource($alert->fresh()),
                'Alerta desactivada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Snooze an alert
     * POST /api/alerts/{alert}/snooze
     */
    public function snooze(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para posponer esta alerta.', 403);
            }

            $request->validate([
                'hours' => 'sometimes|integer|min:1|max:168', // Max 1 week
            ]);

            $hours = $request->input('hours', 24);
            $this->alertService->snoozeAlert($alert, $hours);

            return $this->successResponse(
                new AlertResource($alert->fresh()),
                "Alerta pospuesta por {$hours} horas."
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Unsnooze an alert
     * POST /api/alerts/{alert}/unsnooze
     */
    public function unsnooze(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para reactivar esta alerta.', 403);
            }

            $this->alertService->unsnoozeAlert($alert);

            return $this->successResponse(
                new AlertResource($alert->fresh()),
                'Alerta reactivada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Manually trigger/test an alert
     * POST /api/alerts/{alert}/test
     */
    public function test(Request $request, Alert $alert): JsonResponse
    {
        try {
            // Verify ownership
            if (!$alert->belongsToUser($request->user())) {
                return $this->errorResponse('No tienes permiso para probar esta alerta.', 403);
            }

            $triggered = $this->alertService->triggerAlert($alert);

            if ($triggered) {
                return $this->successResponse(
                    new AlertResource($alert->fresh()),
                    'Alerta de prueba enviada exitosamente.'
                );
            }

            return $this->errorResponse('No se pudo enviar la alerta de prueba.', 500);
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    // ==================== Alert Preferences ====================

    /**
     * Get user's alert preferences
     * GET /api/alerts/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $preferences = $this->alertService->getOrCreatePreferences($user);

            return $this->successResponse(
                new AlertPreferenceResource($preferences),
                'Preferencias obtenidas exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Update user's alert preferences
     * PUT /api/alerts/preferences
     */
    public function updatePreferences(UpdateAlertPreferencesRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $preferences = $this->alertService->updatePreferences($user, $validated);

            return $this->successResponse(
                new AlertPreferenceResource($preferences),
                'Preferencias actualizadas exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    // ==================== Notifications ====================

    /**
     * Get unread notifications for authenticated user
     * GET /api/alerts/notifications/unread
     */
    public function getUnreadNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = $request->query('limit', 50);

            $notifications = $this->alertService->getUnreadNotifications($user, $limit);

            return $this->successResponse(
                AlertNotificationResource::collection($notifications),
                'Notificaciones sin leer obtenidas exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Mark a notification as read
     * PATCH /api/alerts/notifications/{notification}/read
     */
    public function markNotificationAsRead(Request $request, AlertNotification $notification): JsonResponse
    {
        try {
            // Verify ownership
            if ($notification->user_id !== $request->user()->id) {
                return $this->errorResponse('No tienes permiso para marcar esta notificación.', 403);
            }

            $success = $this->alertService->markNotificationAsRead($notification);

            if (!$success) {
                return $this->errorResponse('Esta notificación no se puede marcar como leída.', 422);
            }

            return $this->successResponse(
                new AlertNotificationResource($notification->fresh()),
                'Notificación marcada como leída.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Get available alert types
     * GET /api/alerts/types
     */
    public function getAlertTypes(): JsonResponse
    {
        try {
            $types = Alert::availableTypes();
            $frequencies = Alert::availableFrequencies();

            return $this->successResponse([
                'types' => $types,
                'frequencies' => $frequencies,
            ], 'Tipos de alerta obtenidos exitosamente.');
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }
}