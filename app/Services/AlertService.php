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

    // ==================== Alert Evaluation ====================

    /**
     * Evaluate all active alerts for a user
     */
    public function evaluateAlertsForUser(User $user): array
    {
        $alerts = Alert::forUser($user)->readyToTrigger()->get();
        $triggered = [];

        foreach ($alerts as $alert) {
            if ($this->evaluateAlert($alert)) {
                $triggered[] = $alert;
            }
        }

        return $triggered;
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

    private function evaluateRecurringTransactionMissed(Alert $alert): bool
    {
        // TODO: Implement when recurrence system is ready
        // Check if expected recurring transaction was not created within expected timeframe
        return false;
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

    // ==================== Message Generation ====================

    private function generateAlertMessage(Alert $alert): string
    {
        return match ($alert->type) {
            'budget_threshold' => $this->generateBudgetThresholdMessage($alert),
            'budget_exceeded' => $this->generateBudgetExceededMessage($alert),
            'payment_due' => $this->generatePaymentDueMessage($alert),
            'low_balance' => $this->generateLowBalanceMessage($alert),
            default => "Alerta: {$alert->name}",
        };
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

    // ==================== Validation ====================

    private function validateConditions(string $type, array $conditions): void
    {
        match ($type) {
            'budget_threshold', 'budget_exceeded' => $this->validateBudgetConditions($conditions),
            'payment_due' => $this->validatePaymentDueConditions($conditions),
            'low_balance' => $this->validateLowBalanceConditions($conditions),
            default => null,
        };
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
}