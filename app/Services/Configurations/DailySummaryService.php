<?php

namespace App\Services\Configurations;

use App\Models\Configurations\Transaction;
use App\Models\Personal\User;
use App\Models\Configurations\DailySummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailySummaryService
{
    /**
     * Generar resumen diario para un usuario
     */
    public function generateDailySummary(User $user, ?Carbon $date = null, string $channel = 'whatsapp'): DailySummary
    {
        $date = $date ?? now()->subDay(); // Por defecto, día anterior
        $dateString = $date->toDateString();

        try {
            DB::beginTransaction();

            // Obtener o crear resumen
            $summary = DailySummary::getOrCreate($user, $dateString, $channel);

            // Si ya fue enviado, no regenerar
            if ($summary->isSent()) {
                Log::channel('audit')->info('Daily summary already sent', [
                    'user_id' => $user->id,
                    'date' => $dateString,
                    'channel' => $channel,
                ]);
                DB::commit();
                return $summary;
            }

            // Calcular estadísticas del día
            $stats = $this->calculateDayStats($user, $date);

            // Actualizar resumen
            $summary->update([
                'transactions_count' => $stats['transactions_count'],
                'total_expense_cents' => $stats['total_expense_cents'],
                'total_income_cents' => $stats['total_income_cents'],
                'currency_code' => $user->currency_code,
            ]);

            Log::channel('audit')->info('Daily summary generated', [
                'user_id' => $user->id,
                'date' => $dateString,
                'transactions' => $stats['transactions_count'],
                'channel' => $channel,
            ]);

            DB::commit();
            return $summary->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error generating daily summary', [
                'user_id' => $user->id,
                'date' => $dateString,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calcular estadísticas del día
     */
    protected function calculateDayStats(User $user, Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Obtener transacciones del día
        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->get();

        $totalExpense = 0;
        $totalIncome = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'expense') {
                $totalExpense += $transaction->amount_cents;
            } elseif ($transaction->type === 'income') {
                $totalIncome += $transaction->amount_cents;
            }
            // Transfers no se cuentan en expense/income
        }

        return [
            'transactions_count' => $transactions->count(),
            'total_expense_cents' => $totalExpense,
            'total_income_cents' => $totalIncome,
        ];
    }

    /**
     * Generar resumen con detalles adicionales (top categorías, etc.)
     */
    public function generateDetailedStats(User $user, Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Estadísticas básicas
        $basicStats = $this->calculateDayStats($user, $date);

        // Top categorías de gastos
        $topExpenseCategories = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->whereNotNull('category_id')
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($transactions) {
                return [
                    'category' => $transactions->first()->category?->name ?? 'Sin categoría',
                    'amount_cents' => $transactions->sum('amount_cents'),
                    'count' => $transactions->count(),
                ];
            })
            ->sortByDesc('amount_cents')
            ->take(3)
            ->values();

        // Métodos de pago más usados
        $topPaymentMethods = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->whereNotNull('payment_method_id')
            ->with('paymentMethod')
            ->get()
            ->groupBy('payment_method_id')
            ->map(function ($transactions) {
                return [
                    'payment_method' => $transactions->first()->paymentMethod?->label ?? 'Desconocido',
                    'amount_cents' => $transactions->sum('amount_cents'),
                    'count' => $transactions->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(3)
            ->values();

        // Comparar con día anterior
        $previousDayStats = $this->calculateDayStats($user, $date->copy()->subDay());

        $expenseChange = $basicStats['total_expense_cents'] - $previousDayStats['total_expense_cents'];
        $expenseChangePercent = $previousDayStats['total_expense_cents'] > 0
            ? round(($expenseChange / $previousDayStats['total_expense_cents']) * 100, 2)
            : 0;

        return [
            'date' => $date->toDateString(),
            'transactions_count' => $basicStats['transactions_count'],
            'total_expense_cents' => $basicStats['total_expense_cents'],
            'total_income_cents' => $basicStats['total_income_cents'],
            'net_cents' => $basicStats['total_income_cents'] - $basicStats['total_expense_cents'],
            'top_expense_categories' => $topExpenseCategories,
            'top_payment_methods' => $topPaymentMethods,
            'previous_day' => [
                'expense_cents' => $previousDayStats['total_expense_cents'],
                'change_cents' => $expenseChange,
                'change_percent' => $expenseChangePercent,
            ],
        ];
    }

    /**
     * Obtener resúmenes de un usuario en un rango de fechas
     */
    public function getSummaries(User $user, Carbon $startDate, Carbon $endDate, ?string $channel = null): Collection
    {
        $query = DailySummary::forUser($user)
            ->whereBetween('summary_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('summary_date', 'desc');

        if ($channel) {
            $query->byChannel($channel);
        }

        return $query->get();
    }

    /**
     * Verificar si el usuario debe recibir resumen
     */
    public function shouldSendSummary(User $user, Carbon $date): bool
    {
        // Verificar que el usuario es premium
        if (!$user->is_premium) {
            return false;
        }

        // Verificar que tuvo actividad el día
        $stats = $this->calculateDayStats($user, $date);

        // Solo enviar si tuvo al menos 1 transacción
        return $stats['transactions_count'] > 0;
    }

    /**
     * Marcar resumen como enviado
     */
    public function markAsSent(DailySummary $summary, ?string $templateName = null): void
    {
        $summary->markAsSent($templateName);

        Log::channel('audit')->info('Daily summary marked as sent', [
            'summary_id' => $summary->id,
            'user_id' => $summary->user_id,
            'date' => $summary->summary_date,
            'channel' => $summary->channel,
        ]);
    }

    /**
     * Generar mensaje de texto del resumen para WhatsApp/Email
     */
    public function generateSummaryMessage(DailySummary $summary, array $detailedStats = []): string
    {
        $user = $summary->user;
        $date = Carbon::parse($summary->summary_date)->locale('es')->isoFormat('D [de] MMMM');

        $message = "🗓️ *Resumen de {$date}*\n\n";

        if (!$summary->hasActivity()) {
            $message .= "No registraste transacciones este día.\n";
            return $message;
        }

        // Transacciones
        $message .= "📊 *Movimientos del día:*\n";
        $message .= "  • {$summary->transactions_count} transacción(es)\n\n";

        // Gastos
        if ($summary->total_expense_cents > 0) {
            $message .= "💸 *Gastos:* {$summary->getFormattedExpense()}\n";
        }

        // Ingresos
        if ($summary->total_income_cents > 0) {
            $message .= "💰 *Ingresos:* {$summary->getFormattedIncome()}\n";
        }

        // Balance neto
        $netAmount = $summary->getNetAmount();
        $netEmoji = $netAmount >= 0 ? '✅' : '⚠️';
        $message .= "\n{$netEmoji} *Balance:* {$summary->getFormattedNet()}\n";

        // Top categorías si están disponibles
        if (!empty($detailedStats['top_expense_categories'])) {
            $message .= "\n📁 *Top Categorías:*\n";
            foreach ($detailedStats['top_expense_categories'] as $category) {
                $amount = number_format($category['amount_cents'] / 100, 2);
                $message .= "  • {$category['category']}: {$amount} {$summary->currency_code}\n";
            }
        }

        // Comparación con día anterior
        if (!empty($detailedStats['previous_day'])) {
            $prevDay = $detailedStats['previous_day'];
            if ($prevDay['change_cents'] != 0) {
                $changeEmoji = $prevDay['change_cents'] > 0 ? '📈' : '📉';
                $changeText = abs($prevDay['change_cents']) / 100;
                $message .= "\n{$changeEmoji} Vs. ayer: ";
                $message .= $prevDay['change_cents'] > 0 ? '+' : '-';
                $message .= number_format($changeText, 2) . " {$summary->currency_code}";
                $message .= " ({$prevDay['change_percent']}%)\n";
            }
        }

        return $message;
    }

    /**
     * Obtener resúmenes pendientes de envío
     */
    public function getPendingSummaries(?string $channel = null): Collection
    {
        $query = DailySummary::pending();

        if ($channel) {
            $query->byChannel($channel);
        }

        return $query->with('user')->get();
    }
}