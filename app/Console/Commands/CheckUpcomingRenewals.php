<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

/**
 * CheckUpcomingRenewals Command
 *
 * Archivo: app/Console/Commands/Subscriptions/CheckUpcomingRenewals.php
 *
 * Verificar renovaciones próximas y enviar notificaciones
 *
 * Usage: php artisan subscriptions:check-renewals [--days=3]
 */
class CheckUpcomingRenewals extends Command
{
    protected $signature = 'subscriptions:check-renewals {--days=3 : Días de anticipación}';

    protected $description = 'Verificar renovaciones próximas y notificar a usuarios';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $daysAhead = (int) $this->option('days');

        $this->info("🔍 Verificando renovaciones en los próximos {$daysAhead} días...");

        $notifications = $subscriptionService->checkUpcomingRenewals($daysAhead);

        if (count($notifications) > 0) {
            $this->table(
                ['Usuario', 'Plan', 'Renueva en', 'Días', 'Monto'],
                collect($notifications)->map(fn($n) => [
                    $n['user_id'],
                    $n['plan_name'],
                    $n['renews_at'],
                    $n['days_remaining'],
                    '$' . number_format($n['amount'], 2),
                ])
            );

            $this->info("✅ {count($notifications)} renovación(es) próxima(s)");
        } else {
            $this->info('✅ No hay renovaciones próximas');
        }

        return self::SUCCESS;
    }
}
