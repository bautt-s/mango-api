<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

/**
 * CancelSubscription Command
 *
 * Archivo: app/Console/Commands/Subscriptions/CancelSubscription.php
 *
 * Cancelar suscripción de un usuario
 *
 * Usage: php artisan subscriptions:cancel {user_id} [--immediate]
 */
class CancelSubscription extends Command
{
    protected $signature = 'subscriptions:cancel
{user_id : ID del usuario}
{--immediate : Cancelar inmediatamente sin esperar al final del período}';

    protected $description = 'Cancelar la suscripción de un usuario';

    public function handle(SubscriptionService $subscriptionService): int
    {
        try {
            $userId = $this->argument('user_id');
            $immediate = $this->option('immediate');

            $user = \App\Models\Personal\User::findOrFail($userId);
            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->firstOrFail();

            $this->warn("Cancelando suscripción de: {$user->email}");
            $this->info("Plan: {$subscription->plan->name}");
            $this->info("Tipo: " . ($immediate ? 'INMEDIATA' : 'Al final del período'));

            if (!$this->confirm('¿Deseas continuar?')) {
                $this->warn('Operación cancelada');
                return self::FAILURE;
            }

            $subscriptionService->cancelSubscription($subscription, $immediate);

            $this->info("✅ Suscripción cancelada exitosamente");

            if (!$immediate) {
                $this->info("El usuario mantendrá acceso hasta: {$subscription->fresh()->ends_at}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
