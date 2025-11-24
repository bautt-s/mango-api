<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

/**
 * CheckTrialExpiry Command
 * 
 * Archivo: app/Console/Commands/Subscriptions/CheckTrialExpiry.php
 * 
 * Verificar y expirar trials vencidos
 * 
 * Usage: php artisan subscriptions:check-trial-expiry
 */
class CheckTrialExpiry extends Command
{
    protected $signature = 'subscriptions:check-trial-expiry';

    protected $description = 'Verificar y expirar períodos de prueba vencidos';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('🔍 Verificando trials expirados...');

        $expiredCount = $subscriptionService->checkTrialExpiry();

        if ($expiredCount > 0) {
            $this->warn("⚠️  {$expiredCount} trial(s) expirado(s)");
        } else {
            $this->info('✅ No hay trials expirados');
        }

        return self::SUCCESS;
    }
}