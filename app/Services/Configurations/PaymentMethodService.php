<?php

namespace App\Services\Configurations;

use App\Models\Configurations\PaymentMethod;
use App\Models\Personal\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentMethodService
{
    /**
     * Get all payment methods for a user
     */
    public function getPaymentMethodsForUser(User $user): Collection
    {
        return PaymentMethod::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new payment method for a user
     */
    public function createPaymentMethod(User $user, array $data): PaymentMethod
    {
        DB::beginTransaction();
        try {
            // If this is being set as default, unset other defaults
            if ($data['is_default'] ?? false) {
                $this->unsetAllDefaults($user);
            }

            // If no default exists and this is the first payment method, make it default
            $hasDefault = PaymentMethod::where('user_id', $user->id)
                ->where('is_default', true)
                ->exists();

            if (!$hasDefault) {
                $data['is_default'] = true;
            }

            $paymentMethod = PaymentMethod::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'label' => $data['label'] ?? null,
                'issuer' => $data['issuer'] ?? null,
                'network' => $data['network'] ?? null,
                'last4' => $data['last4'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            Log::channel('audit')->info('Payment method created', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
            ]);

            DB::commit();
            return $paymentMethod->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating payment method', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Update an existing payment method
     */
    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        DB::beginTransaction();
        try {
            // If setting as default, unset other defaults
            if (isset($data['is_default']) && $data['is_default']) {
                $this->unsetAllDefaults($paymentMethod->user);
            }

            $paymentMethod->update($data);

            Log::channel('audit')->info('Payment method updated', [
                'user_id' => $paymentMethod->user_id,
                'payment_method_id' => $paymentMethod->id,
            ]);

            DB::commit();
            return $paymentMethod->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error updating payment method', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Delete a payment method
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        DB::beginTransaction();
        try {
            $userId = $paymentMethod->user_id;
            $wasDefault = $paymentMethod->is_default;

            $paymentMethod->delete();

            // If deleted was default, set another as default
            if ($wasDefault) {
                $this->ensureDefaultExists($paymentMethod->user);
            }

            Log::channel('audit')->info('Payment method deleted', [
                'user_id' => $userId,
                'payment_method_id' => $paymentMethod->id,
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error deleting payment method', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Set a payment method as default
     */
    public function setAsDefault(PaymentMethod $paymentMethod): PaymentMethod
    {
        DB::beginTransaction();
        try {
            // Unset all other defaults for this user
            $this->unsetAllDefaults($paymentMethod->user);

            // Set this one as default
            $paymentMethod->is_default = true;
            $paymentMethod->save();

            Log::channel('audit')->info('Payment method set as default', [
                'user_id' => $paymentMethod->user_id,
                'payment_method_id' => $paymentMethod->id,
            ]);

            DB::commit();
            return $paymentMethod->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error setting payment method as default', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Set billing cycle information for a credit card
     */
    public function setBillingCycle(PaymentMethod $paymentMethod, array $cycleData): PaymentMethod
    {
        DB::beginTransaction();
        try {
            $metadata = $paymentMethod->metadata ?? [];

            $metadata['billing_cycle'] = [
                'billing_cycle_day' => $cycleData['billing_cycle_day'],
                'due_day' => $cycleData['due_day'],
                'credit_limit_cents' => $cycleData['credit_limit_cents'] ?? null,
                'alert_days_before' => $cycleData['alert_days_before'] ?? null,
            ];

            $paymentMethod->metadata = $metadata;
            $paymentMethod->save();

            Log::channel('audit')->info('Billing cycle set for payment method', [
                'user_id' => $paymentMethod->user_id,
                'payment_method_id' => $paymentMethod->id,
            ]);

            DB::commit();
            return $paymentMethod->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error setting billing cycle', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Get the next due date for a payment method with billing cycle
     */
    public function getNextDueDate(PaymentMethod $paymentMethod): ?Carbon
    {
        if (!$paymentMethod->metadata || !isset($paymentMethod->metadata['billing_cycle'])) {
            return null;
        }

        $billingCycle = $paymentMethod->metadata['billing_cycle'];
        $dueDay = $billingCycle['due_day'];
        $today = Carbon::today();

        $nextDue = Carbon::create($today->year, $today->month, $dueDay);

        // If the due date has passed this month, move to next month
        if ($nextDue->lessThan($today)) {
            $nextDue->addMonth();
        }

        // Handle months with fewer days (e.g., day 31 in February)
        if ($nextDue->day !== $dueDay) {
            $nextDue = $nextDue->endOfMonth();
        }

        return $nextDue;
    }

    /**
     * Get current billing period spending for a credit card
     */
    public function getCurrentPeriodSpending(PaymentMethod $paymentMethod): int
    {
        if (!$paymentMethod->metadata || !isset($paymentMethod->metadata['billing_cycle'])) {
            return 0;
        }

        $billingCycle = $paymentMethod->metadata['billing_cycle'];
        $billingDay = $billingCycle['billing_cycle_day'];

        $today = Carbon::today();
        $periodStart = Carbon::create($today->year, $today->month, $billingDay);

        // If we haven't reached the billing day this month, use last month's billing day
        if ($periodStart->greaterThan($today)) {
            $periodStart->subMonth();
        }

        $periodEnd = (clone $periodStart)->addMonth();

        // Sum all transactions for this payment method in the current period
        return $paymentMethod->transactions()
            ->where('type', 'expense')
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->sum('amount_cents');
    }

    /**
     * Unset all default payment methods for a user
     */
    protected function unsetAllDefaults(User $user): void
    {
        PaymentMethod::where('user_id', $user->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Ensure at least one default payment method exists
     */
    protected function ensureDefaultExists(User $user): void
    {
        $hasDefault = PaymentMethod::where('user_id', $user->id)
            ->where('is_default', true)
            ->exists();

        if (!$hasDefault) {
            $firstMethod = PaymentMethod::where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($firstMethod) {
                $firstMethod->is_default = true;
                $firstMethod->save();
            }
        }
    }
}