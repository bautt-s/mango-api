<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummaryJob;
use App\Models\Personal\User;
use App\Services\Configurations\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailySummariesCommand extends Command
{
    protected $signature = 'summaries:generate 
                            {--date= : Fecha específica (YYYY-MM-DD), por defecto ayer}
                            {--user= : ID de usuario específico}
                            {--channel=whatsapp : Canal de envío (whatsapp, email)}
                            {--send : Enviar inmediatamente en lugar de encolar}
                            {--force : Regenerar incluso si ya existe}';

    protected $description = 'Generar y enviar resúmenes diarios a usuarios premium';

    protected DailySummaryService $summaryService;

    public function __construct(DailySummaryService $summaryService)
    {
        parent::__construct();
        $this->summaryService = $summaryService;
    }

    public function handle(): int
    {
        $this->info('🗓️  Generando resúmenes diarios...');
        $this->newLine();

        // Parsear opciones
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $userId = $this->option('user');
        $channel = $this->option('channel');
        $shouldSend = $this->option('send');
        $force = $this->option('force');

        $this->info("📅 Fecha: {$date->toDateString()}");
        $this->info("📱 Canal: {$channel}");
        $this->newLine();

        // Obtener usuarios a procesar
        $users = $this->getUsers($userId);

        if ($users->isEmpty()) {
            $this->warn('⚠️  No se encontraron usuarios para procesar.');
            return self::FAILURE;
        }

        $this->info("👥 Usuarios a procesar: {$users->count()}");
        $this->newLine();

        // Procesar cada usuario
        $generated = 0;
        $sent = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            try {
                // Verificar si debe enviar resumen
                if (!$force && !$this->summaryService->shouldSendSummary($user, $date)) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Generar resumen
                $summary = $this->summaryService->generateDailySummary($user, $date, $channel);
                $generated++;

                // Enviar si está configurado
                if ($shouldSend && $summary->hasActivity()) {
                    if ($this->option('send')) {
                        // Enviar inmediatamente (síncrono)
                        $this->sendSummary($summary);
                    } else {
                        // Encolar para envío asíncrono
                        SendDailySummaryJob::dispatch($summary);
                    }
                    $sent++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("\n❌ Error procesando usuario {$user->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Resumen final
        $this->info('✅ Proceso completado:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Usuarios procesados', $users->count()],
                ['Resúmenes generados', $generated],
                ['Resúmenes enviados/encolados', $sent],
                ['Omitidos (sin actividad)', $skipped],
                ['Errores', $errors],
            ]
        );

        return self::SUCCESS;
    }

    protected function getUsers(?string $userId)
    {
        if ($userId) {
            // Usuario específico
            $user = User::find($userId);
            return $user ? collect([$user]) : collect();
        }

        // Todos los usuarios premium activos
        return User::where('is_premium', true)
            ->whereNull('deleted_at')
            ->get();
    }

    protected function sendSummary($summary): void
    {
        // Obtener estadísticas detalladas
        $detailedStats = $this->summaryService->generateDetailedStats(
            $summary->user,
            Carbon::parse($summary->summary_date)
        );

        // Generar mensaje
        $message = $this->summaryService->generateSummaryMessage($summary, $detailedStats);

        // Aquí iría la lógica de envío real según el canal
        // Por ahora solo marcar como enviado
        $this->summaryService->markAsSent($summary, 'default_template');

        // Log del mensaje generado (para debugging)
        $this->line("\n📨 Mensaje para {$summary->user->username}:");
        $this->line($message);
        $this->newLine();
    }
}