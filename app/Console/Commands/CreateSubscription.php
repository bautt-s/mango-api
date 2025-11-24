<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

/**
 * CreateSubscription Command
 * 
 * 
 * Crear suscripción manualmente para un usuario
 * 
 * Usage: php artisan subscriptions:create {user_id} {plan_code}
 */
class CreateSubscription extends Command
{
    protected $signature = 'subscriptions:create
                            {user_id : ID del usuario}
                            {plan_code : Código del plan}
                            {--preapproval= : ID de preaprobación de MercadoPago}';

    protected $description = 'Crear una suscripción manualmente para un usuario';

    public function handle(SubscriptionService $subscriptionService): int
    {
        try {
            $userId = $this->argument('user_id');
            $planCode = $this->argument('plan_code');
            $preapprovalId = $this->option('preapproval');

            $user = \App\Models\Personal\User::findOrFail($userId);
            $plan = \App\Models\Subscriptions\Plan::where('code', $planCode)->firstOrFail();

            $this->info("Creando suscripción para: {$user->email}");
            $this->info("Plan: {$plan->name} ({$plan->interval})");

            if (!$this->confirm('¿Deseas continuar?')) {
                $this->warn('Operación cancelada');
                return self::FAILURE;
            }

            $subscription = $subscriptionService->createSubscription(
                user: $user,
                plan: $plan,
                preapprovalId: $preapprovalId
            );

            $this->info("✅ Suscripción creada exitosamente");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID', $subscription->id],
                    ['Usuario', $user->email],
                    ['Plan', $plan->name],
                    ['Estado', $subscription->status],
                    ['Inicia', $subscription->started_at],
                    ['Renueva', $subscription->renews_at],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
