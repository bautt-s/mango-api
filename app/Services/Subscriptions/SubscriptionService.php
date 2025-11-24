<?php

namespace App\Services\Subscriptions;

use App\Models\Personal\User;
use App\Models\Subscriptions\Plan;
use App\Models\Subscriptions\Subscription;
use App\Models\Subscriptions\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Crear una nueva suscripción para un usuario
     */
    public function createSubscription(User $user, Plan $plan, ?string $preapprovalId = null): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $preapprovalId) {
            // Cancelar cualquier suscripción activa existente
            $existingSubscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->first();

            if ($existingSubscription) {
                $this->cancelSubscription($existingSubscription, immediate: false);
            }

            // Determinar fechas según el plan
            $startedAt = now();
            $renewsAt = $this->calculateRenewalDate($startedAt, $plan->interval);

            // Crear la suscripción
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'provider' => 'mercadopago',
                'provider_preapproval_id' => $preapprovalId,
                'status' => 'active',
                'started_at' => $startedAt,
                'renews_at' => $renewsAt,
            ]);

            // Actualizar usuario como premium
            $user->update([
                'is_premium' => true,
                'premium_since' => $startedAt,
                'trial_ends_at' => null,
            ]);

            Log::info("Suscripción creada", [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_code' => $plan->code,
            ]);

            return $subscription->fresh(['plan', 'user']);
        });
    }

    /**
     * Iniciar período de prueba para un usuario
     */
    public function startTrial(User $user, int $trialDays = 14): Subscription
    {
        return DB::transaction(function () use ($user, $trialDays) {
            // Obtener plan free o crear uno temporal
            $freePlan = Plan::where('code', 'free')->first();

            if (!$freePlan) {
                throw new \Exception("Plan 'free' no encontrado");
            }

            $trialEndsAt = now()->addDays($trialDays);

            // Crear suscripción en estado trial
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
                'provider' => 'mercadopago',
                'status' => 'trialing',
                'started_at' => now(),
                'renews_at' => $trialEndsAt,
                'ends_at' => $trialEndsAt,
            ]);

            // Actualizar usuario
            $user->update([
                'is_premium' => true,
                'trial_ends_at' => $trialEndsAt,
                'premium_since' => now(),
            ]);

            Log::info("Trial iniciado", [
                'user_id' => $user->id,
                'trial_days' => $trialDays,
                'expires_at' => $trialEndsAt,
            ]);

            return $subscription->fresh(['plan', 'user']);
        });
    }

    /**
     * Cancelar una suscripción
     */
    public function cancelSubscription(Subscription $subscription, bool $immediate = false): bool
    {
        return DB::transaction(function () use ($subscription, $immediate) {
            if ($immediate) {
                // Cancelación inmediata
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => now(),
                ]);

                // Remover premium del usuario
                $subscription->user->update([
                    'is_premium' => false,
                ]);
            } else {
                // Cancelación al final del período pagado
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => $subscription->renews_at,
                ]);

                // Usuario mantiene acceso hasta la fecha de renovación
            }

            Log::info("Suscripción cancelada", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'immediate' => $immediate,
                'ends_at' => $subscription->ends_at,
            ]);

            return true;
        });
    }

    /**
     * Reanudar una suscripción cancelada
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        if ($subscription->status !== 'canceled') {
            throw new \Exception("Solo se pueden reanudar suscripciones canceladas");
        }

        // Verificar que aún no ha expirado
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            throw new \Exception("La suscripción ya expiró. Debe crear una nueva.");
        }

        return DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'active',
                'canceled_at' => null,
                'ends_at' => null,
            ]);

            $subscription->user->update([
                'is_premium' => true,
            ]);

            Log::info("Suscripción reanudada", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);

            return true;
        });
    }

    /**
     * Cambiar plan de suscripción
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan) {
            if ($subscription->plan_id === $newPlan->id) {
                throw new \Exception("El usuario ya está en este plan");
            }

            $oldPlan = $subscription->plan;

            // Calcular prórroga si es necesario (upgrade/downgrade)
            $proratedAmount = $this->calculateProration($subscription, $newPlan);

            // Actualizar suscripción
            $subscription->update([
                'plan_id' => $newPlan->id,
                'renews_at' => $this->calculateRenewalDate(now(), $newPlan->interval),
            ]);

            Log::info("Plan cambiado", [
                'subscription_id' => $subscription->id,
                'old_plan' => $oldPlan->code,
                'new_plan' => $newPlan->code,
                'prorated_amount' => $proratedAmount,
            ]);

            return $subscription->fresh(['plan', 'user']);
        });
    }

    /**
     * Procesar pago desde webhook de MercadoPago
     */
    public function processPayment(array $webhookData): SubscriptionPayment
    {
        return DB::transaction(function () use ($webhookData) {
            // Extraer datos relevantes del webhook
            $subscriptionId = $webhookData['subscription_id'] ?? null;
            $status = $webhookData['status'] ?? 'pending';
            $amountCents = $webhookData['amount_cents'] ?? 0;
            $currencyCode = $webhookData['currency_code'] ?? 'ARS';

            $subscription = Subscription::findOrFail($subscriptionId);

            // Crear registro de pago
            $payment = SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'status' => $status,
                'amount_cents' => $amountCents,
                'currency_code' => $currencyCode,
                'raw_payload' => $webhookData,
            ]);

            // Actualizar estado de suscripción según resultado
            if ($status === 'paid') {
                $this->handleSuccessfulPayment($subscription);
            } elseif ($status === 'failed') {
                $this->handleFailedPayment($subscription);
            }

            Log::info("Pago procesado", [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'status' => $status,
                'amount' => $amountCents / 100,
            ]);

            return $payment;
        });
    }

    /**
     * Manejar pago exitoso
     */
    protected function handleSuccessfulPayment(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'active',
            'renews_at' => $this->calculateRenewalDate(now(), $subscription->plan->interval),
        ]);

        $subscription->user->update([
            'is_premium' => true,
        ]);
    }

    /**
     * Manejar pago fallido
     */
    public function handleFailedPayment(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'past_due',
        ]);

        Log::warning("Pago fallido", [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);

        // TODO: Enviar notificación al usuario
    }

    /**
     * Verificar y expirar trials vencidos
     */
    public function checkTrialExpiry(): int
    {
        $expiredCount = 0;

        $expiredTrials = Subscription::where('status', 'trialing')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expiredTrials as $subscription) {
            DB::transaction(function () use ($subscription) {
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

                $subscription->user->update([
                    'is_premium' => false,
                    'trial_ends_at' => null,
                ]);
            });

            $expiredCount++;

            Log::info("Trial expirado", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        }

        return $expiredCount;
    }

    /**
     * Verificar suscripciones que están por expirar
     */
    public function checkUpcomingRenewals(int $daysAhead = 3): array
    {
        $upcomingDate = now()->addDays($daysAhead);

        $subscriptions = Subscription::where('status', 'active')
            ->whereBetween('renews_at', [now(), $upcomingDate])
            ->with(['user', 'plan'])
            ->get();

        $notifications = [];

        foreach ($subscriptions as $subscription) {
            $daysRemaining = now()->diffInDays($subscription->renews_at);

            $notifications[] = [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_name' => $subscription->plan->name,
                'renews_at' => $subscription->renews_at,
                'days_remaining' => $daysRemaining,
                'amount' => $subscription->plan->price,
            ];

            Log::info("Renovación próxima", [
                'subscription_id' => $subscription->id,
                'days_remaining' => $daysRemaining,
            ]);
        }

        return $notifications;
    }

    /**
     * Obtener estadísticas de suscripciones
     */
    public function getStatistics(): array
    {
        return [
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'trialing_subscriptions' => Subscription::where('status', 'trialing')->count(),
            'canceled_subscriptions' => Subscription::where('status', 'canceled')->count(),
            'past_due_subscriptions' => Subscription::where('status', 'past_due')->count(),
            'total_premium_users' => User::where('is_premium', true)->count(),
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
            'churn_rate' => $this->calculateChurnRate(),
        ];
    }

    /**
     * Calcular MRR (Monthly Recurring Revenue)
     */
    protected function calculateMRR(): float
    {
        $monthlyRevenue = Subscription::where('status', 'active')
            ->whereHas('plan', function ($query) {
                $query->where('interval', 'monthly');
            })
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price_cents');

        $annualRevenue = Subscription::where('status', 'active')
            ->whereHas('plan', function ($query) {
                $query->where('interval', 'annual');
            })
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price_cents');

        // Convertir revenue anual a mensual
        $annualMRR = $annualRevenue / 12;

        return ($monthlyRevenue + $annualMRR) / 100; // Convertir de centavos a unidades
    }

    /**
     * Calcular ARR (Annual Recurring Revenue)
     */
    protected function calculateARR(): float
    {
        return $this->calculateMRR() * 12;
    }

    /**
     * Calcular tasa de churn (últimos 30 días)
     */
    protected function calculateChurnRate(): float
    {
        $thirtyDaysAgo = now()->subDays(30);

        $canceledCount = Subscription::where('status', 'canceled')
            ->where('canceled_at', '>=', $thirtyDaysAgo)
            ->count();

        $activeCount = Subscription::where('status', 'active')->count();

        if ($activeCount === 0) {
            return 0;
        }

        return round(($canceledCount / $activeCount) * 100, 2);
    }

    /**
     * Calcular fecha de renovación según intervalo
     */
    protected function calculateRenewalDate(Carbon $startDate, string $interval): Carbon
    {
        return match ($interval) {
            'monthly' => $startDate->copy()->addMonth(),
            'annual' => $startDate->copy()->addYear(),
            default => throw new \Exception("Intervalo no soportado: {$interval}"),
        };
    }

    /**
     * Calcular prorrateo en cambio de plan
     */
    protected function calculateProration(Subscription $subscription, Plan $newPlan): int
    {
        $currentPlan = $subscription->plan;

        // Días restantes en el período actual
        $daysRemaining = now()->diffInDays($subscription->renews_at);
        $totalDays = $subscription->started_at->diffInDays($subscription->renews_at);

        if ($totalDays === 0) {
            return 0;
        }

        // Valor no usado del plan actual
        $unusedValue = ($currentPlan->price_cents * $daysRemaining) / $totalDays;

        // Costo proporcional del nuevo plan
        $newPlanProrated = ($newPlan->price_cents * $daysRemaining) / $totalDays;

        // Diferencia a cobrar/acreditar
        return (int) ($newPlanProrated - $unusedValue);
    }

    /**
     * Obtener historial de pagos de una suscripción
     */
    public function getPaymentHistory(Subscription $subscription): array
    {
        $payments = $subscription->payments()
            ->orderBy('created_at', 'desc')
            ->get();

        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount_cents / 100,
                'currency' => $payment->currency_code,
                'status' => $payment->status,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}