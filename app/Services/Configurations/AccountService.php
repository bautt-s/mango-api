<?php

namespace App\Services\Configurations;

use App\Models\Configurations\Account;
use App\Models\Personal\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccountService
{
    /**
     * Create a new account for user
     */
    public function createAccount(User $user, array $data): Account
    {
        DB::beginTransaction();

        try {
            // Check if this is user's first account
            $isFirstAccount = $user->accounts()->count() === 0;

            // Set as default if first account or explicitly requested
            if ($isFirstAccount || ($data['is_default'] ?? false)) {
                // Unset other defaults
                $user->accounts()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            // Set sort_order to max + 1 if not provided
            if (!isset($data['sort_order'])) {
                $maxOrder = $user->accounts()->max('sort_order') ?? -1;
                $data['sort_order'] = $maxOrder + 1;
            }

            // Validate label uniqueness for this user
            $existingAccount = $user->accounts()
                ->where('label', $data['label'])
                ->first();

            if ($existingAccount) {
                throw new \Exception('Ya existe una cuenta con este nombre');
            }

            $account = $user->accounts()->create($data);

            DB::commit();

            return $account->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Update an existing account
     */
    public function updateAccount(Account $account, array $data): Account
    {
        DB::beginTransaction();

        try {
            // Validate label uniqueness if label is being updated
            if (isset($data['label']) && $data['label'] !== $account->label) {
                $existingAccount = Account::where('user_id', $account->user_id)
                    ->where('label', $data['label'])
                    ->where('id', '!=', $account->id)
                    ->first();

                if ($existingAccount) {
                    throw new \Exception('Ya existe una cuenta con este nombre');
                }
            }

            $account->update($data);

            DB::commit();

            return $account->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Archive an account
     */
    public function archiveAccount(Account $account): bool
    {
        return $account->archive();
    }

    /**
     * Unarchive an account
     */
    public function unarchiveAccount(Account $account): Account
    {
        $account->unarchive();
        return $account->fresh();
    }

    /**
     * Set account as default
     */
    public function setAsDefault(Account $account): bool
    {
        // Cannot set archived account as default
        if ($account->archived) {
            return false;
        }

        return $account->makeDefault();
    }

    /**
     * Reorder accounts atomically
     */
    public function reorderAccounts(User $user, array $ordering): bool
    {
        DB::beginTransaction();

        try {
            foreach ($ordering as $item) {
                Account::where('id', $item['id'])
                    ->where('user_id', $user->id)
                    ->update(['sort_order' => $item['sort_order']]);
            }

            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Calculate account balance in cents
     */
    public function calculateBalance(Account $account): int
    {
        return $account->getBalance();
    }

    /**
     * Get accounts for user (with optional archived)
     */
    public function getAccountsForUser(User $user, bool $includeArchived = false): Collection
    {
        $query = $user->accounts();

        if (!$includeArchived) {
            $query->active();
        }

        return $query->ordered()->get();
    }

    /**
     * Get active accounts ordered
     */
    public function getActiveAccounts(User $user): Collection
    {
        return $user->accounts()
            ->active()
            ->ordered()
            ->get();
    }
}
