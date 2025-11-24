<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

/**
 * SubscriptionStatistics Command
 * 
 * Archivo: app/Console/Commands/Subscriptions/SubscriptionStatistics.php
 * 
 * Mostrar estadísticas de suscripciones
 * 
 * Usage: php artisan subscriptions:statistics
 */
class SubscriptionStatistics extends Command
{
    protected $signature = 'subscriptions:statistics';

    protected $description = 'Mostrar estadísticas de suscripciones';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('📊 Estadísticas de Suscripciones');
        $this->newLine();

        $stats = $subscriptionService->getStatistics();

        // Tabla de conteos
        $this->info('📈 Conteos:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de suscripciones', $stats['total_subscriptions']],
                ['Suscripciones activas', $stats['active_subscriptions']],
                ['En período de prueba', $stats['trialing_subscriptions']],
                ['Canceladas', $stats['canceled_subscriptions']],
                ['Pagos vencidos', $stats['past_due_subscriptions']],
                ['Usuarios premium', $stats['total_premium_users']],
            ]
        );

        $this->newLine();

        // Tabla de ingresos
        $this->info('💰 Ingresos:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['MRR (Ingreso Recurrente Mensual)', '$' . number_format($stats['mrr'], 2)],
                ['ARR (Ingreso Recurrente Anual)', '$' . number_format($stats['arr'], 2)],
                ['Tasa de Churn (últimos 30 días)', $stats['churn_rate'] . '%'],
            ]
        );

        return self::SUCCESS;
    }
}
