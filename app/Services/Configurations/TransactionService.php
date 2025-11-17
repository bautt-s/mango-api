<?php

namespace App\Services\Configurations;

use App\Models\Configurations\Account;
use App\Models\Configurations\Category;
use App\Models\Configurations\PaymentMethod;
use App\Models\Configurations\Transaction;
use App\Models\Personal\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionService
{
    /**
     * Create an expense transaction
     */
    public function createExpense(User $user, array $data): Transaction
    {
        DB::beginTransaction();
        try {
            // Validate account belongs to user and is not archived
            $account = Account::where('id', $data['account_id'])
                ->where('user_id', $user->id)
                ->where('archived', false)
                ->first();

            if (!$account) {
                throw new \Exception('La cuenta no existe o está archivada.');
            }

            // Validate category if provided
            if (isset($data['category_id'])) {
                $this->validateCategory($user, $data['category_id'], 'expense');
            }

            // Validate payment method if provided
            if (isset($data['payment_method_id'])) {
                $this->validatePaymentMethod($user, $data['payment_method_id']);
            }

            // Normalize tags
            $normalizedTags = isset($data['tags'])
                ? $this->normalizeAndStoreTags($data['tags'])
                : null;

            // Generate recurrence group ID if needed
            if (($data['is_recurring'] ?? false) && !isset($data['recurrence_group_id'])) {
                $data['recurrence_group_id'] = (string) Str::uuid();
            }

            $transaction = new Transaction([
                'user_id' => $user->id,
                'type' => 'expense',
                'amount_cents' => $data['amount_cents'],
                'currency_code' => $data['currency_code'] ?? $user->currency_code,
                'occurred_at' => $data['occurred_at'],
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_group_id' => $data['recurrence_group_id'] ?? null,
            ]);

            // Set encrypted fields
            $transaction->description = $data['description'] ?? null;
            $transaction->merchant = $data['merchant'] ?? null;
            $transaction->notes = $data['notes'] ?? null;
            $transaction->tags = $normalizedTags;

            $transaction->save();

            Log::channel('audit')->info('Expense transaction created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount_cents' => $transaction->amount_cents,
            ]);

            DB::commit();
            return $transaction->fresh(['account', 'category', 'paymentMethod']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating expense transaction', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Create an income transaction
     */
    public function createIncome(User $user, array $data): Transaction
    {
        DB::beginTransaction();
        try {
            // Validate account belongs to user and is not archived
            $account = Account::where('id', $data['account_id'])
                ->where('user_id', $user->id)
                ->where('archived', false)
                ->first();

            if (!$account) {
                throw new \Exception('La cuenta no existe o está archivada.');
            }

            // Validate category if provided
            if (isset($data['category_id'])) {
                $this->validateCategory($user, $data['category_id'], 'income');
            }

            // Normalize tags
            $normalizedTags = isset($data['tags'])
                ? $this->normalizeAndStoreTags($data['tags'])
                : null;

            // Generate recurrence group ID if needed
            if (($data['is_recurring'] ?? false) && !isset($data['recurrence_group_id'])) {
                $data['recurrence_group_id'] = (string) Str::uuid();
            }

            $transaction = new Transaction([
                'user_id' => $user->id,
                'type' => 'income',
                'amount_cents' => $data['amount_cents'],
                'currency_code' => $data['currency_code'] ?? $user->currency_code,
                'occurred_at' => $data['occurred_at'],
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_group_id' => $data['recurrence_group_id'] ?? null,
            ]);

            // Set encrypted fields
            $transaction->description = $data['description'] ?? null;
            $transaction->notes = $data['notes'] ?? null;
            $transaction->tags = $normalizedTags;

            $transaction->save();

            Log::channel('audit')->info('Income transaction created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount_cents' => $transaction->amount_cents,
            ]);

            DB::commit();
            return $transaction->fresh(['account', 'category']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating income transaction', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Create a transfer transaction
     */
    public function createTransfer(User $user, array $data): Transaction
    {
        DB::beginTransaction();
        try {
            // Validate both accounts
            $sourceAccount = Account::where('id', $data['source_account_id'])
                ->where('user_id', $user->id)
                ->where('archived', false)
                ->first();

            $targetAccount = Account::where('id', $data['target_account_id'])
                ->where('user_id', $user->id)
                ->where('archived', false)
                ->first();

            if (!$sourceAccount || !$targetAccount) {
                throw new \Exception('Una o ambas cuentas no existen o están archivadas.');
            }

            // Validate accounts are different
            if ($sourceAccount->id === $targetAccount->id) {
                throw new \Exception('Las cuentas de origen y destino deben ser diferentes.');
            }

            // Validate same currency (current limitation)
            if ($sourceAccount->currency_code !== $targetAccount->currency_code) {
                throw new \Exception('Las transferencias solo están permitidas entre cuentas con la misma moneda.');
            }

            // Normalize tags
            $normalizedTags = isset($data['tags'])
                ? $this->normalizeAndStoreTags($data['tags'])
                : null;

            $transaction = new Transaction([
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount_cents' => $data['amount_cents'],
                'currency_code' => $sourceAccount->currency_code,
                'occurred_at' => $data['occurred_at'],
                'source_account_id' => $data['source_account_id'],
                'target_account_id' => $data['target_account_id'],
            ]);

            // Set encrypted fields
            $transaction->description = $data['description'] ?? null;
            $transaction->notes = $data['notes'] ?? null;
            $transaction->tags = $normalizedTags;

            $transaction->save();

            Log::channel('audit')->info('Transfer transaction created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount_cents' => $transaction->amount_cents,
            ]);

            DB::commit();
            return $transaction->fresh(['sourceAccount', 'targetAccount']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating transfer transaction', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Update an existing transaction
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        DB::beginTransaction();
        try {
            // Prevent type changes
            if (isset($data['type']) && $data['type'] !== $transaction->type) {
                throw new \Exception('No se puede cambiar el tipo de transacción.');
            }

            // Prevent account changes for transfers
            if ($transaction->isTransfer()) {
                if (isset($data['source_account_id']) || isset($data['target_account_id'])) {
                    throw new \Exception('No se pueden cambiar las cuentas de una transferencia.');
                }
            }

            // Validate category if being updated
            if (isset($data['category_id'])) {
                $this->validateCategory($transaction->user, $data['category_id'], $transaction->type);
            }

            // Validate payment method if being updated
            if (isset($data['payment_method_id'])) {
                $this->validatePaymentMethod($transaction->user, $data['payment_method_id']);
            }

            // Normalize tags if provided
            if (isset($data['tags'])) {
                $data['tags'] = $this->normalizeAndStoreTags($data['tags']);
            }

            // Update basic fields
            $fillableFields = ['amount_cents', 'currency_code', 'occurred_at', 'category_id', 'payment_method_id', 'is_recurring', 'recurrence_group_id'];
            foreach ($fillableFields as $field) {
                if (isset($data[$field])) {
                    $transaction->$field = $data[$field];
                }
            }

            // Update encrypted fields if provided
            if (isset($data['description'])) {
                $transaction->description = $data['description'];
            }
            if (isset($data['merchant'])) {
                $transaction->merchant = $data['merchant'];
            }
            if (isset($data['notes'])) {
                $transaction->notes = $data['notes'];
            }
            if (isset($data['tags'])) {
                $transaction->tags = $data['tags'];
            }

            $transaction->save();

            Log::channel('audit')->info('Transaction updated', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
            ]);

            DB::commit();
            return $transaction->fresh(['account', 'sourceAccount', 'targetAccount', 'category', 'paymentMethod']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error updating transaction', [
                'transaction_id' => $transaction->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Delete a transaction (soft delete)
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        DB::beginTransaction();
        try {
            $transaction->delete();

            Log::channel('audit')->info('Transaction deleted', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error deleting transaction', [
                'transaction_id' => $transaction->id,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Update tags for a transaction
     */
    public function updateTags(Transaction $transaction, array $tags): Transaction
    {
        DB::beginTransaction();
        try {
            $normalizedTags = $this->normalizeAndStoreTags($tags);
            $transaction->tags = $normalizedTags;
            $transaction->save();

            Log::channel('audit')->info('Transaction tags updated', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
            ]);

            DB::commit();
            return $transaction->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Search transactions with filters
     */
    public function searchTransactions(User $user, array $filters): LengthAwarePaginator
    {
        $query = Transaction::where('user_id', $user->id)
            ->with(['account', 'sourceAccount', 'targetAccount', 'category', 'paymentMethod']);

        // Date filters
        if (isset($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to']);
        }

        // Type filter
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Account filter (includes transfers)
        if (isset($filters['account_id'])) {
            $query->byAccount($filters['account_id']);
        }

        // Category filter
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Payment method filter
        if (isset($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        // Amount filters
        if (isset($filters['min_amount_cents'])) {
            $query->where('amount_cents', '>=', $filters['min_amount_cents']);
        }
        if (isset($filters['max_amount_cents'])) {
            $query->where('amount_cents', '<=', $filters['max_amount_cents']);
        }

        // Search term (using blind index)
        if (isset($filters['search_term'])) {
            $query->searchDescription($filters['search_term']);
        }

        // Recurring filter
        if (isset($filters['is_recurring'])) {
            $query->where('is_recurring', $filters['is_recurring']);
        }

        // Order by occurred_at descending
        $query->orderBy('occurred_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get recurring transaction groups with statistics
     */
    public function getRecurringGroups(User $user): array
    {
        $groups = Transaction::where('user_id', $user->id)
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_group_id')
            ->with(['account', 'category'])
            ->get()
            ->groupBy('recurrence_group_id');

        $result = [];
        foreach ($groups as $groupId => $transactions) {
            $result[] = [
                'recurrence_group_id' => $groupId,
                'count' => $transactions->count(),
                'type' => $transactions->first()->type,
                'average_amount_cents' => (int) $transactions->avg('amount_cents'),
                'total_amount_cents' => $transactions->sum('amount_cents'),
                'first_transaction' => $transactions->sortBy('occurred_at')->first(),
                'last_transaction' => $transactions->sortByDesc('occurred_at')->first(),
                'transactions' => $transactions->sortByDesc('occurred_at')->values(),
            ];
        }

        return $result;
    }

    /**
     * Normalize and store tags
     */
    public function normalizeAndStoreTags(array $tags): array
    {
        // Convert to lowercase
        $normalized = array_map('mb_strtolower', $tags);

        // Remove duplicates
        $normalized = array_unique($normalized);

        // Sort alphabetically
        sort($normalized);

        // Limit to 10 tags
        return array_slice($normalized, 0, 10);
    }

    /**
     * Validate category belongs to user and matches transaction type
     */
    protected function validateCategory(User $user, string $categoryId, string $transactionType): void
    {
        $category = Category::where('id', $categoryId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('is_system', true);
            })
            ->first();

        if (!$category) {
            throw new \Exception('La categoría no existe o no te pertenece.');
        }

        // Validate category kind matches transaction type
        if ($category->kind !== 'both' && $category->kind !== $transactionType) {
            throw new \Exception('La categoría no es válida para este tipo de transacción.');
        }
    }

    /**
     * Validate payment method belongs to user
     */
    protected function validatePaymentMethod(User $user, string $paymentMethodId): void
    {
        $exists = PaymentMethod::where('id', $paymentMethodId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$exists) {
            throw new \Exception('El método de pago no existe o no te pertenece.');
        }
    }
}