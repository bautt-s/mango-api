<?php

namespace App\Services;

use App\Models\Configurations\Account;
use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertNotification;
use App\Models\Alerts\AlertPreference;
use App\Models\Configurations\Budget;
use App\Models\Configurations\PaymentMethod;
use App\Models\Personal\User;
use App\Services\Configurations\BudgetService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function __construct(
        private BudgetService $budgetService,
    ) {}

    // ==================== Alert CRUD ====================

    /**
     * Get all alerts for a user
     */
    public function getAlertsForUser(User $user, ?string $type = null, ?bool $activeOnly = null): Collection
    {
        $query = Alert::forUser($user)->with(['notifications' => fn($q) => $q->latest()->limit(5)]);

        if ($type) {
            $query->byType($type);
        }

        if ($activeOnly !== null) {
            $activeOnly ? $query->active() : $query->inactive();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new alert
     */
    public function createAlert(User $user, array $data): Alert
    {
        DB::beginTransaction();
        try {
            // Validate conditions based on alert type
            $this->validateConditions($data['type'], $data['conditions']);

            $alert = Alert::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'conditions' => $data['conditions'],
                'metadata' => $data['metadata'] ?? null,
                'active' => $data['active'] ?? true,
                'frequency' => $data['frequency'] ?? 'once',
            ]);

            Log::channel('audit')->info('Alert created', [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
                'type' => $alert->type,
            ]);

            DB::commit();
            return $alert->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create alert', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing alert
     */
    public function updateAlert(Alert $alert, array $data): Alert
    {
        DB::beginTransaction();
        try {
            // Validate conditions if type changed or conditions updated
            if (isset($data['conditions']) || isset($data['type'])) {
                $type = $data['type'] ?? $alert->type;
                $conditions = $data['conditions'] ?? $alert->conditions;
                $this->validateConditions($type, $conditions);
            }

            $alert->update($data);

            Log::channel('audit')->info('Alert updated', [
                'user_id' => $alert->user_id,
                'alert_id' => $alert->id,
            ]);

            DB::commit();
            return $alert->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete an alert
     */
    public function deleteAlert(Alert $alert): bool
    {
        DB::beginTransaction();
        try {
            $alertId = $alert->id;
            $userId = $alert->user_id;

            $alert->delete();

            Log::channel('audit')->info('Alert deleted', [
                'user_id' => $userId,
                'alert_id' => $alertId,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Evaluate a specific alert
     */
    public function evaluateAlert(Alert $alert): bool
    {
        if (!$alert->canTrigger()) {
            return false;
        }

        $shouldTrigger = match ($alert->type) {
            'budget_threshold' => $this->evaluateBudgetThreshold($alert),
            'budget_exceeded' => $this->evaluateBudgetExceeded($alert),
            'payment_due' => $this->evaluatePaymentDue($alert),
            'low_balance' => $this->evaluateLowBalance($alert),
            'recurring_transaction_missed' => $this->evaluateRecurringTransactionMissed($alert),
            default => false,
        };

        if ($shouldTrigger) {
            return $this->triggerAlert($alert);
        }

        return false;
    }

    /**
     * Trigger an alert and send notifications
     */
    public function triggerAlert(Alert $alert): bool
    {
        DB::beginTransaction();
        try {
            // Get user preferences
            $preferences = $this->getOrCreatePreferences($alert->user);

            // Get message content
            $message = $this->generateAlertMessage($alert);

            // Get preferred channels
            $channels = $preferences->getPreferredChannels($alert->type);

            // Create notification records
            foreach ($channels as $channel) {
                if ($preferences->canSendNotification($alert->type, $channel)) {
                    $notification = AlertNotification::createPending($alert, $channel, $message);

                    // Send notification based on channel
                    $this->sendNotification($notification);
                }
            }

            // Mark alert as triggered
            $alert->markTriggered();

            Log::channel('audit')->info('Alert triggered', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'type' => $alert->type,
                'channels' => $channels,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to trigger alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ==================== Type-Specific Evaluators ====================

    private function evaluateBudgetThreshold(Alert $alert): bool
    {
        $budgetId = $alert->getConditionValue('budget_id');
        $threshold = $alert->getConditionValue('threshold_percentage', 80);

        if (!$budgetId) {
            return false;
        }

        $budget = Budget::find($budgetId);
        if (!$budget || $budget->user_id !== $alert->user_id) {
            return false;
        }

        $spentCents = $this->budgetService->calculateSpent($budget);
        $percentageUsed = ($spentCents / $budget->limit_cents) * 100;

        return $percentageUsed >= $threshold;
    }

    private function evaluateBudgetExceeded(Alert $alert): bool
    {
        $budgetId = $alert->getConditionValue('budget_id');

        if (!$budgetId) {
            return false;
        }

        $budget = Budget::find($budgetId);
        if (!$budget || $budget->user_id !== $alert->user_id) {
            return false;
        }

        $spentCents = $this->budgetService->calculateSpent($budget);

        return $spentCents > $budget->limit_cents;
    }

    private function evaluatePaymentDue(Alert $alert): bool
    {
        $paymentMethodId = $alert->getConditionValue('payment_method_id');
        $daysBefore = $alert->getConditionValue('days_before', 3);

        if (!$paymentMethodId) {
            return false;
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);
        if (!$paymentMethod || $paymentMethod->user_id !== $alert->user_id) {
            return false;
        }

        // Check if payment method has billing cycle
        $billingDay = $paymentMethod->metadata['billing_day'] ?? null;
        if (!$billingDay) {
            return false;
        }

        // Calculate next due date
        $today = now();
        $currentMonth = $today->copy()->day($billingDay);

        if ($today->day > $billingDay) {
            $nextDueDate = $currentMonth->addMonth();
        } else {
            $nextDueDate = $currentMonth;
        }

        $daysUntilDue = $today->diffInDays($nextDueDate, false);

        return $daysUntilDue <= $daysBefore && $daysUntilDue >= 0;
    }

    private function evaluateLowBalance(Alert $alert): bool
    {
        $accountId = $alert->getConditionValue('account_id');
        $thresholdCents = $alert->getConditionValue('threshold_cents', 10000); // Default 100 ARS

        if (!$accountId) {
            return false;
        }

        $account = Account::find($accountId);
        if (!$account || $account->user_id !== $alert->user_id) {
            return false;
        }

        $balance = $account->getBalance();

        return $balance <= $thresholdCents;
    }

    // ==================== Alert Preferences ====================

    public function getOrCreatePreferences(User $user): AlertPreference
    {
        $preferences = AlertPreference::where('user_id', $user->id)->first();

        if (!$preferences) {
            $preferences = AlertPreference::createDefault($user);
        }

        return $preferences;
    }

    public function updatePreferences(User $user, array $data): AlertPreference
    {
        DB::beginTransaction();
        try {
            $preferences = $this->getOrCreatePreferences($user);
            $preferences->update($data);

            Log::channel('audit')->info('Alert preferences updated', [
                'user_id' => $user->id,
            ]);

            DB::commit();
            return $preferences->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update alert preferences', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ==================== Notification Sending ====================

    private function sendNotification(AlertNotification $notification): void
    {
        try {
            match ($notification->channel) {
                'email' => $this->sendEmailNotification($notification),
                'whatsapp' => $this->sendWhatsAppNotification($notification),
                'in_app' => $this->sendInAppNotification($notification),
                default => Log::warning('Unknown notification channel', ['channel' => $notification->channel]),
            };
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            Log::error('Failed to send notification', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmailNotification(AlertNotification $notification): void
    {
        // TODO: Implement email sending via Laravel Mail
        // For now, just mark as sent
        $notification->markAsSent();

        Log::info('Email notification sent (placeholder)', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
        ]);
    }

    private function sendWhatsAppNotification(AlertNotification $notification): void
    {
        // TODO: Implement WhatsApp sending via WhatsApp Cloud API
        // For now, just mark as sent
        $notification->markAsSent();

        Log::info('WhatsApp notification sent (placeholder)', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
        ]);
    }

    private function sendInAppNotification(AlertNotification $notification): void
    {
        // In-app notifications are just stored in DB
        // Frontend will poll or use websockets to display them
        $notification->markAsSent();

        Log::info('In-app notification created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
        ]);
    }

    private function generateBudgetThresholdMessage(Alert $alert): string
    {
        $budgetId = $alert->getConditionValue('budget_id');
        $threshold = $alert->getConditionValue('threshold_percentage', 80);

        $budget = Budget::find($budgetId);
        if (!$budget) {
            return "Has alcanzado el {$threshold}% de tu presupuesto.";
        }

        $spentCents = $this->budgetService->calculateSpent($budget);
        $percentage = round(($spentCents / $budget->limit_cents) * 100, 1);
        $spent = number_format($spentCents / 100, 2);
        $limit = number_format($budget->limit_cents / 100, 2);

        return "⚠️ Presupuesto '{$budget->name}': Has gastado {$spent} {$budget->currency_code} de {$limit} ({$percentage}%)";
    }

    private function generateBudgetExceededMessage(Alert $alert): string
    {
        $budgetId = $alert->getConditionValue('budget_id');

        $budget = Budget::find($budgetId);
        if (!$budget) {
            return "Has excedido tu presupuesto.";
        }

        $spentCents = $this->budgetService->calculateSpent($budget);
        $exceededCents = $spentCents - $budget->limit_cents;
        $spent = number_format($spentCents / 100, 2);
        $limit = number_format($budget->limit_cents / 100, 2);
        $exceeded = number_format($exceededCents / 100, 2);

        return "🚨 Presupuesto '{$budget->name}' EXCEDIDO: Gastaste {$spent} {$budget->currency_code} (límite: {$limit}). Te pasaste por {$exceeded}.";
    }

    private function generatePaymentDueMessage(Alert $alert): string
    {
        $paymentMethodId = $alert->getConditionValue('payment_method_id');
        $daysBefore = $alert->getConditionValue('days_before', 3);

        $paymentMethod = PaymentMethod::find($paymentMethodId);
        if (!$paymentMethod) {
            return "Tienes un pago próximo a vencer.";
        }

        $label = $paymentMethod->label ?: $paymentMethod->type;

        return "💳 Recordatorio: El vencimiento de '{$label}' está próximo (en {$daysBefore} días).";
    }

    private function generateLowBalanceMessage(Alert $alert): string
    {
        $accountId = $alert->getConditionValue('account_id');
        $thresholdCents = $alert->getConditionValue('threshold_cents', 10000);

        $account = Account::find($accountId);
        if (!$account) {
            return "El saldo de una cuenta está bajo.";
        }

        $balance = $account->getBalance();
        $balanceFormatted = number_format($balance / 100, 2);
        $threshold = number_format($thresholdCents / 100, 2);

        return "⚠️ Saldo bajo en '{$account->label}': {$balanceFormatted} {$account->currency_code} (umbral: {$threshold})";
    }

    private function validateBudgetConditions(array $conditions): void
    {
        if (!isset($conditions['budget_id'])) {
            throw new \InvalidArgumentException('El ID del presupuesto es requerido.');
        }

        $budget = Budget::find($conditions['budget_id']);
        if (!$budget) {
            throw new \InvalidArgumentException('El presupuesto especificado no existe.');
        }
    }

    private function validatePaymentDueConditions(array $conditions): void
    {
        if (!isset($conditions['payment_method_id'])) {
            throw new \InvalidArgumentException('El ID del método de pago es requerido.');
        }

        $paymentMethod = PaymentMethod::find($conditions['payment_method_id']);
        if (!$paymentMethod) {
            throw new \InvalidArgumentException('El método de pago especificado no existe.');
        }
    }

    private function validateLowBalanceConditions(array $conditions): void
    {
        if (!isset($conditions['account_id'])) {
            throw new \InvalidArgumentException('El ID de la cuenta es requerido.');
        }

        if (!isset($conditions['threshold_cents']) || $conditions['threshold_cents'] < 0) {
            throw new \InvalidArgumentException('El umbral de saldo debe ser un número positivo.');
        }

        $account = Account::find($conditions['account_id']);
        if (!$account) {
            throw new \InvalidArgumentException('La cuenta especificada no existe.');
        }
    }

    // ==================== Utility Methods ====================

    public function snoozeAlert(Alert $alert, int $hours = 24): bool
    {
        return $alert->snooze($hours);
    }

    public function unsnoozeAlert(Alert $alert): bool
    {
        return $alert->unsnooze();
    }

    public function getUnreadNotifications(User $user, int $limit = 50): Collection
    {
        return AlertNotification::where('user_id', $user->id)
            ->unread()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markNotificationAsRead(AlertNotification $notification): bool
    {
        if ($notification->channel !== 'in_app') {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Evaluate if a milestone has been reached
     * 
     * Conditions required:
     * - milestone_code: string (code of the milestone to check)
     * 
     * Triggers when: User reaches a specific milestone
     */
    private function evaluateMilestoneReached(Alert $alert): bool
    {
        $milestoneCode = $alert->getConditionValue('milestone_code');

        if (!$milestoneCode) {
            return false;
        }

        // Check if milestone exists and has been reached recently
        $milestone = \App\Models\Personal\Milestone::where('user_id', $alert->user_id)
            ->where('code', $milestoneCode)
            ->whereNotNull('reached_at')
            ->first();

        if (!$milestone) {
            return false;
        }

        // Only trigger if milestone was reached after the last alert trigger
        // or if this is the first time the alert is evaluated
        if ($alert->last_triggered_at) {
            return $milestone->reached_at->isAfter($alert->last_triggered_at);
        }

        // If never triggered, check if milestone was reached in the last 24 hours
        return $milestone->reached_at->isAfter(now()->subDay());
    }

    /**
     * Evaluate if there's unusual spending in a category
     * 
     * Conditions required:
     * - category_id: uuid (optional, null for all categories)
     * - threshold_percentage: int (percentage above average to trigger, default 150)
     * - lookback_days: int (days to calculate average, default 30)
     * 
     * Triggers when: Single transaction amount exceeds threshold % of average
     */
    private function evaluateUnusualSpending(Alert $alert): bool
    {
        $categoryId = $alert->getConditionValue('category_id');
        $thresholdPercentage = $alert->getConditionValue('threshold_percentage', 150);
        $lookbackDays = $alert->getConditionValue('lookback_days', 30);

        // Get date range for analysis
        $startDate = now()->subDays($lookbackDays);
        $endDate = now();

        // Build query for historical transactions
        $query = \App\Models\Configurations\Transaction::where('user_id', $alert->user_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Calculate average transaction amount (excluding outliers)
        $transactions = $query->get();

        if ($transactions->count() < 5) {
            // Not enough data to determine unusual spending
            return false;
        }

        // Calculate average (excluding top 10% as outliers)
        $amounts = $transactions->pluck('amount_cents')->sort()->values();
        $excludeCount = (int) ceil($amounts->count() * 0.1);
        $trimmedAmounts = $amounts->slice(0, $amounts->count() - $excludeCount);

        $averageAmount = $trimmedAmounts->average();

        if ($averageAmount <= 0) {
            return false;
        }

        // Check if there are recent transactions exceeding the threshold
        $thresholdAmount = $averageAmount * ($thresholdPercentage / 100);

        $recentUnusual = \App\Models\Configurations\Transaction::where('user_id', $alert->user_id)
            ->where('type', 'expense')
            ->where('amount_cents', '>', $thresholdAmount);

        if ($categoryId) {
            $recentUnusual->where('category_id', $categoryId);
        }

        // Check transactions since last trigger or last 24 hours
        if ($alert->last_triggered_at) {
            $recentUnusual->where('occurred_at', '>', $alert->last_triggered_at);
        } else {
            $recentUnusual->where('occurred_at', '>', now()->subDay());
        }

        $unusualTransaction = $recentUnusual->first();

        if ($unusualTransaction) {
            // Store transaction details in alert metadata for message generation
            $alert->metadata = array_merge($alert->metadata ?? [], [
                'last_unusual_transaction_id' => $unusualTransaction->id,
                'last_unusual_amount_cents' => $unusualTransaction->amount_cents,
                'calculated_average_cents' => (int) $averageAmount,
                'threshold_amount_cents' => (int) $thresholdAmount,
            ]);
            $alert->save();

            return true;
        }

        return false;
    }

    /**
     * Evaluate if a recurring transaction was missed
     * 
     * Conditions required:
     * - recurrence_group_id: uuid (group ID of recurring transactions)
     * - tolerance_days: int (days of tolerance before alerting, default 2)
     * 
     * Triggers when: Expected recurring transaction hasn't occurred within tolerance period
     */
    private function evaluateRecurringTransactionMissed(Alert $alert): bool
    {
        $recurrenceGroupId = $alert->getConditionValue('recurrence_group_id');
        $toleranceDays = $alert->getConditionValue('tolerance_days', 2);

        if (!$recurrenceGroupId) {
            return false;
        }

        // Get all transactions in the recurrence group
        $transactions = \App\Models\Configurations\Transaction::where('user_id', $alert->user_id)
            ->where('recurrence_group_id', $recurrenceGroupId)
            ->orderBy('occurred_at', 'desc')
            ->get();

        if ($transactions->count() < 2) {
            // Need at least 2 transactions to establish a pattern
            return false;
        }

        // Calculate average interval between transactions
        $intervals = [];
        for ($i = 0; $i < $transactions->count() - 1; $i++) {
            $current = $transactions[$i];
            $previous = $transactions[$i + 1];

            $intervalDays = $current->occurred_at->diffInDays($previous->occurred_at);
            $intervals[] = $intervalDays;
        }

        if (empty($intervals)) {
            return false;
        }

        // Calculate average interval (excluding outliers)
        sort($intervals);
        $count = count($intervals);
        $excludeCount = (int) ceil($count * 0.2); // Exclude top/bottom 10% each

        if ($count > 4) {
            $trimmedIntervals = array_slice($intervals, $excludeCount, $count - (2 * $excludeCount));
        } else {
            $trimmedIntervals = $intervals;
        }

        $averageInterval = array_sum($trimmedIntervals) / count($trimmedIntervals);

        // Get the last transaction
        $lastTransaction = $transactions->first();
        $daysSinceLastTransaction = now()->diffInDays($lastTransaction->occurred_at);

        // Expected next transaction date with tolerance
        $expectedMaxDays = $averageInterval + $toleranceDays;

        // Check if we've passed the expected date plus tolerance
        if ($daysSinceLastTransaction > $expectedMaxDays) {
            // Store details for message generation
            $alert->metadata = array_merge($alert->metadata ?? [], [
                'last_transaction_id' => $lastTransaction->id,
                'last_transaction_date' => $lastTransaction->occurred_at->toDateString(),
                'days_since_last' => $daysSinceLastTransaction,
                'expected_interval_days' => (int) $averageInterval,
                'days_overdue' => (int) ($daysSinceLastTransaction - $averageInterval),
            ]);
            $alert->save();

            return true;
        }

        return false;
    }

    // ==================== AGREGAR ESTOS GENERADORES DE MENSAJES ====================

    private function generateMilestoneReachedMessage(Alert $alert): string
    {
        $milestoneCode = $alert->getConditionValue('milestone_code');

        $milestone = \App\Models\Personal\Milestone::where('user_id', $alert->user_id)
            ->where('code', $milestoneCode)
            ->whereNotNull('reached_at')
            ->first();

        if (!$milestone) {
            return "¡Has alcanzado un hito!";
        }

        $title = $milestone->title;
        $description = $milestone->description;

        return "🏆 ¡Felicitaciones! Has alcanzado el hito '{$title}': {$description}";
    }

    private function generateUnusualSpendingMessage(Alert $alert): string
    {
        $categoryId = $alert->getConditionValue('category_id');
        $metadata = $alert->metadata ?? [];

        $unusualAmount = $metadata['last_unusual_amount_cents'] ?? 0;
        $averageAmount = $metadata['calculated_average_cents'] ?? 0;

        $unusualFormatted = number_format($unusualAmount / 100, 2);
        $averageFormatted = number_format($averageAmount / 100, 2);

        $currency = $alert->user->currency_code;

        if ($categoryId) {
            $category = \App\Models\Configurations\Category::find($categoryId);
            $categoryName = $category ? $category->name : 'una categoría';

            return "⚠️ Gasto inusual detectado en '{$categoryName}': {$unusualFormatted} {$currency} (promedio: {$averageFormatted})";
        }

        return "⚠️ Gasto inusual detectado: {$unusualFormatted} {$currency} (tu promedio es {$averageFormatted})";
    }

    private function generateRecurringTransactionMissedMessage(Alert $alert): string
    {
        $metadata = $alert->metadata ?? [];

        $daysOverdue = $metadata['days_overdue'] ?? 0;
        $lastDate = $metadata['last_transaction_date'] ?? 'fecha desconocida';

        // Try to get transaction details
        $transactionId = $metadata['last_transaction_id'] ?? null;
        $transactionLabel = 'transacción recurrente';

        if ($transactionId) {
            $transaction = \App\Models\Configurations\Transaction::find($transactionId);
            if ($transaction && $transaction->description) {
                $transactionLabel = $transaction->description;
            } elseif ($transaction && $transaction->merchant) {
                $transactionLabel = $transaction->merchant;
            } elseif ($transaction && $transaction->category) {
                $transactionLabel = $transaction->category->name;
            }
        }

        return "📅 No se registró la transacción recurrente esperada '{$transactionLabel}'. Última vez: {$lastDate} ({$daysOverdue} días de retraso)";
    }

    /**
     * Generate alert message based on type
     */
    private function generateAlertMessage(Alert $alert): string
    {
        return match ($alert->type) {
            'budget_threshold' => $this->generateBudgetThresholdMessage($alert),
            'budget_exceeded' => $this->generateBudgetExceededMessage($alert),
            'payment_due' => $this->generatePaymentDueMessage($alert),
            'low_balance' => $this->generateLowBalanceMessage($alert),
            'milestone_reached' => $this->generateMilestoneReachedMessage($alert),
            'unusual_spending' => $this->generateUnusualSpendingMessage($alert),
            'recurring_transaction_missed' => $this->generateRecurringTransactionMissedMessage($alert),
            default => "Tienes una nueva notificación.",
        };
    }

    // ==================== ACTUALIZAR EL MÉTODO validateConditions ====================

    private function validateConditions(string $type, array $conditions): void
    {
        match ($type) {
            'budget_threshold', 'budget_exceeded' => $this->validateBudgetConditions($conditions),
            'payment_due' => $this->validatePaymentDueConditions($conditions),
            'low_balance' => $this->validateLowBalanceConditions($conditions),
            'milestone_reached' => $this->validateMilestoneConditions($conditions),
            'unusual_spending' => $this->validateUnusualSpendingConditions($conditions),
            'recurring_transaction_missed' => $this->validateRecurringTransactionMissedConditions($conditions),
            default => null,
        };
    }

    // ==================== AGREGAR VALIDADORES ====================

    private function validateMilestoneConditions(array $conditions): void
    {
        if (!isset($conditions['milestone_code'])) {
            throw new \InvalidArgumentException('El código del hito es requerido.');
        }

        if (!is_string($conditions['milestone_code']) || empty($conditions['milestone_code'])) {
            throw new \InvalidArgumentException('El código del hito debe ser una cadena válida.');
        }
    }

    private function validateUnusualSpendingConditions(array $conditions): void
    {
        // category_id is optional
        if (isset($conditions['category_id'])) {
            $category = \App\Models\Configurations\Category::find($conditions['category_id']);
            if (!$category) {
                throw new \InvalidArgumentException('La categoría especificada no existe.');
            }
        }

        // threshold_percentage is optional but should be valid if provided
        if (isset($conditions['threshold_percentage'])) {
            $threshold = $conditions['threshold_percentage'];
            if (!is_numeric($threshold) || $threshold < 100 || $threshold > 500) {
                throw new \InvalidArgumentException('El porcentaje del umbral debe estar entre 100 y 500.');
            }
        }

        // lookback_days is optional but should be valid if provided
        if (isset($conditions['lookback_days'])) {
            $days = $conditions['lookback_days'];
            if (!is_numeric($days) || $days < 7 || $days > 90) {
                throw new \InvalidArgumentException('Los días de retrospectiva deben estar entre 7 y 90.');
            }
        }
    }

    private function validateRecurringTransactionMissedConditions(array $conditions): void
    {
        if (!isset($conditions['recurrence_group_id'])) {
            throw new \InvalidArgumentException('El ID del grupo de recurrencia es requerido.');
        }

        // Verify that the recurrence group exists and has transactions
        $count = \App\Models\Configurations\Transaction::where('recurrence_group_id', $conditions['recurrence_group_id'])
            ->count();

        if ($count < 2) {
            throw new \InvalidArgumentException('El grupo de recurrencia debe tener al menos 2 transacciones para establecer un patrón.');
        }

        // tolerance_days is optional but should be valid if provided
        if (isset($conditions['tolerance_days'])) {
            $days = $conditions['tolerance_days'];
            if (!is_numeric($days) || $days < 0 || $days > 14) {
                throw new \InvalidArgumentException('Los días de tolerancia deben estar entre 0 y 14.');
            }
        }
    }
}