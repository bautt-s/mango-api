<?php

namespace App\Jobs;

use App\Models\Configurations\DailySummary;
use App\Services\Configurations\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120]; // Reintentos: 30s, 1min, 2min

    protected DailySummary $summary;

    public function __construct(DailySummary $summary)
    {
        $this->summary = $summary;
    }

    public function handle(DailySummaryService $summaryService): void
    {
        try {
            // Verificar que el resumen no haya sido enviado ya
            if ($this->summary->isSent()) {
                Log::info('Daily summary already sent, skipping', [
                    'summary_id' => $this->summary->id,
                    'user_id' => $this->summary->user_id,
                ]);
                return;
            }

            // Verificar que el resumen tenga actividad
            if (!$this->summary->hasActivity()) {
                Log::info('Daily summary has no activity, skipping', [
                    'summary_id' => $this->summary->id,
                    'user_id' => $this->summary->user_id,
                ]);
                return;
            }

            // Obtener estadísticas detalladas
            $detailedStats = $summaryService->generateDetailedStats(
                $this->summary->user,
                Carbon::parse($this->summary->summary_date)
            );

            // Generar mensaje
            $message = $summaryService->generateSummaryMessage($this->summary, $detailedStats);

            // Enviar según el canal
            $this->sendViaChannel($message);

            // Marcar como enviado
            $summaryService->markAsSent($this->summary, $this->getTemplateName());

            Log::info('Daily summary sent successfully', [
                'summary_id' => $this->summary->id,
                'user_id' => $this->summary->user_id,
                'channel' => $this->summary->channel,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error sending daily summary', [
                'summary_id' => $this->summary->id,
                'user_id' => $this->summary->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lanzar excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }

    protected function sendViaChannel(string $message): void
    {
        switch ($this->summary->channel) {
            case 'whatsapp':
                $this->sendViaWhatsApp($message);
                break;

            case 'email':
                $this->sendViaEmail($message);
                break;

            default:
                Log::warning('Unknown channel for daily summary', [
                    'channel' => $this->summary->channel,
                ]);
        }
    }

    protected function sendViaWhatsApp(string $message): void
    {
        // TODO: Implementar envío real vía WhatsApp Cloud API
        // Por ahora solo loggear el mensaje

        Log::info('WhatsApp message ready to send', [
            'user_id' => $this->summary->user_id,
            'phone' => $this->summary->user->phone,
            'message' => $message,
        ]);

        // Placeholder: Aquí iría la integración con WhatsApp Cloud API
        // Example:
        // $this->whatsAppService->sendMessage(
        //     $this->summary->user->phone,
        //     $message
        // );
    }

    protected function sendViaEmail(string $message): void
    {
        // TODO: Implementar envío real vía Email
        // Por ahora solo loggear

        Log::info('Email ready to send', [
            'user_id' => $this->summary->user_id,
            'email' => $this->summary->user->email,
            'subject' => "Resumen diario - {$this->summary->summary_date}",
        ]);

        // Placeholder: Aquí iría el envío de email
        // Example:
        // Mail::to($this->summary->user->email)
        //     ->send(new DailySummaryMail($this->summary, $message));
    }

    protected function getTemplateName(): string
    {
        return match ($this->summary->channel) {
            'whatsapp' => 'whatsapp_daily_summary_v1',
            'email' => 'email_daily_summary_v1',
            default => 'default_template',
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Daily summary job failed permanently', [
            'summary_id' => $this->summary->id,
            'user_id' => $this->summary->user_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Opcional: Notificar al equipo de desarrollo sobre el fallo
        // Notification::route('slack', config('services.slack.webhook'))
        //     ->notify(new DailySummaryFailedNotification($this->summary, $exception));
    }
}
