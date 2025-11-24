<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\CreateSubscriptionRequest;
use App\Http\Requests\Subscriptions\ChangePlanRequest;
use App\Http\Resources\Subscriptions\SubscriptionResource;
use App\Http\Resources\Subscriptions\PlanResource;
use App\Models\Subscriptions\Plan;
use App\Models\Subscriptions\Subscription;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Obtener suscripción actual del usuario
     * 
     * GET /api/v1/subscription
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->with(['plan'])
            ->first();

        if (!$subscription) {
            return $this->successResponse(null, 'No tienes una suscripción activa');
        }

        return $this->successResponse(
            new SubscriptionResource($subscription),
            'Suscripción obtenida exitosamente'
        );
    }

    /**
     * Crear nueva suscripción
     * 
     * POST /api/v1/subscription
     */
    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $plan = Plan::where('code', $validated['plan_code'])
                ->where('active', true)
                ->firstOrFail();

            // Crear suscripción
            $subscription = $this->subscriptionService->createSubscription(
                user: $user,
                plan: $plan,
                preapprovalId: $validated['preapproval_id'] ?? null
            );

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Suscripción creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Iniciar período de prueba
     * 
     * POST /api/v1/subscription/trial
     */
    public function startTrial(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar que no tenga trial activo
            if ($user->trial_ends_at && $user->trial_ends_at->isFuture()) {
                return $this->errorResponse(
                    'Ya tienes un período de prueba activo',
                    [],
                    400
                );
            }

            // Verificar que no haya tenido trial antes
            $hadTrial = Subscription::where('user_id', $user->id)
                ->where('status', 'trialing')
                ->exists();

            if ($hadTrial) {
                return $this->errorResponse(
                    'Ya has usado tu período de prueba',
                    [],
                    400
                );
            }

            $subscription = $this->subscriptionService->startTrial($user);

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Período de prueba iniciado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Cancelar suscripción
     * 
     * DELETE /api/v1/subscription
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->firstOrFail();

            // Determinar si es cancelación inmediata (trial) o al final del período (pago)
            $immediate = $subscription->status === 'trialing';

            $this->subscriptionService->cancelSubscription($subscription, $immediate);

            $message = $immediate
                ? 'Suscripción cancelada inmediatamente'
                : 'Suscripción cancelada. Mantendrás acceso hasta el final del período pagado';

            return $this->successResponse(
                new SubscriptionResource($subscription->fresh()),
                $message
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Reanudar suscripción cancelada
     * 
     * POST /api/v1/subscription/resume
     */
    public function resume(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = $user->subscriptions()
                ->where('status', 'canceled')
                ->whereNotNull('ends_at')
                ->where('ends_at', '>', now())
                ->firstOrFail();

            $this->subscriptionService->resumeSubscription($subscription);

            return $this->successResponse(
                new SubscriptionResource($subscription->fresh()),
                'Suscripción reanudada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Cambiar plan de suscripción
     * 
     * PUT /api/v1/subscription/plan
     */
    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $subscription = $user->subscriptions()
                ->where('status', 'active')
                ->firstOrFail();

            $newPlan = Plan::where('code', $validated['plan_code'])
                ->where('active', true)
                ->firstOrFail();

            $subscription = $this->subscriptionService->changePlan($subscription, $newPlan);

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Plan cambiado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Obtener planes disponibles
     * 
     * GET /api/v1/subscription/plans
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = Plan::where('active', true)
            ->orderBy('price_cents', 'asc')
            ->get();

        return $this->successResponse(
            PlanResource::collection($plans),
            'Planes obtenidos exitosamente'
        );
    }

    /**
     * Obtener historial de pagos
     * 
     * GET /api/v1/subscription/payments
     */
    public function payments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing', 'canceled', 'past_due'])
                ->first();

            if (!$subscription) {
                return $this->successResponse([], 'No tienes historial de pagos');
            }

            $payments = $this->subscriptionService->getPaymentHistory($subscription);

            return $this->successResponse(
                $payments,
                'Historial de pagos obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Verificar estado de la suscripción
     * 
     * GET /api/v1/subscription/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trialing', 'canceled', 'past_due'])
            ->with(['plan'])
            ->first();

        if (!$subscription) {
            return $this->successResponse([
                'has_subscription' => false,
                'is_premium' => false,
                'status' => null,
            ], 'Sin suscripción');
        }

        $data = [
            'has_subscription' => true,
            'is_premium' => $user->is_premium,
            'status' => $subscription->status,
            'is_active' => in_array($subscription->status, ['active', 'trialing']),
            'is_trialing' => $subscription->status === 'trialing',
            'is_canceled' => $subscription->status === 'canceled',
            'is_past_due' => $subscription->status === 'past_due',
            'plan' => [
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'interval' => $subscription->plan->interval,
                'price' => $subscription->plan->price,
                'currency' => $subscription->plan->currency_code,
            ],
            'started_at' => $subscription->started_at?->toDateTimeString(),
            'renews_at' => $subscription->renews_at?->toDateTimeString(),
            'ends_at' => $subscription->ends_at?->toDateTimeString(),
            'canceled_at' => $subscription->canceled_at?->toDateTimeString(),
            'trial_ends_at' => $user->trial_ends_at?->toDateTimeString(),
            'days_remaining' => $subscription->renews_at
                ? now()->diffInDays($subscription->renews_at)
                : null,
        ];

        return $this->successResponse($data, 'Estado de suscripción obtenido');
    }
}