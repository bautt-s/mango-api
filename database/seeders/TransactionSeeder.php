<?php

namespace Database\Seeders;

use App\Models\Configurations\Account;
use App\Models\Configurations\Category;
use App\Models\Configurations\Transaction;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $accounts = $user->accounts;
            $paymentMethods = $user->paymentMethods;
            $expenseCategories = Category::expense()->get();
            $incomeCategories = Category::income()->get();

            if ($accounts->isEmpty() || $paymentMethods->isEmpty()) {
                continue;
            }

            $defaultAccount = $accounts->where('is_default', true)->first() ?? $accounts->first();

            // Generate transactions for the last 90 days
            for ($i = 0; $i < 90; $i++) {
                $date = now()->subDays($i);
                
                // Random number of transactions per day (0-5)
                $dailyTransactions = rand(0, 5);
                
                for ($j = 0; $j < $dailyTransactions; $j++) {
                    $transactionType = $this->randomTransactionType();
                    
                    if ($transactionType === 'expense') {
                        $transaction = new Transaction([
                            'user_id' => $user->id,
                            'type' => 'expense',
                            'amount_cents' => rand(500, 50000),
                            'currency_code' => $user->currency_code,
                            'occurred_at' => $date->copy()->setTime(rand(8, 22), rand(0, 59)),
                            'account_id' => $defaultAccount->id,
                            'category_id' => $expenseCategories->random()->id,
                            'payment_method_id' => $paymentMethods->random()->id,
                            'is_recurring' => rand(0, 10) > 8,
                        ]);
                        
                        // Set encrypted fields - fix here
                        $transaction->description = $this->getExpenseDescription();
                        $transaction->merchant = $this->getMerchant();
                        
                        // FIX: Only set tags if they exist
                        $tags = $this->getRandomTags();
                        if ($tags !== null) {
                            $transaction->tags = $tags;
                        }
                        
                        $transaction->save();
                    } elseif ($transactionType === 'income') {
                        $transaction = new Transaction([
                            'user_id' => $user->id,
                            'type' => 'income',
                            'amount_cents' => rand(10000, 500000),
                            'currency_code' => $user->currency_code,
                            'occurred_at' => $date->copy()->setTime(rand(8, 18), rand(0, 59)),
                            'account_id' => $defaultAccount->id,
                            'category_id' => $incomeCategories->random()->id,
                            'payment_method_id' => null,
                        ]);
                        
                        // Set encrypted fields - fix here
                        $transaction->description = $this->getIncomeDescription();
                        
                        // FIX: Only set tags if they exist
                        $tags = $this->getRandomTags();
                        if ($tags !== null) {
                            $transaction->tags = $tags;
                        }
                        
                        $transaction->save();
                    } else {
                        // Transfer
                        if ($accounts->count() >= 2) {
                            $sourceAccount = $accounts->random();
                            $targetAccount = $accounts->where('id', '!=', $sourceAccount->id)->random();
                            
                            $transaction = new Transaction([
                                'user_id' => $user->id,
                                'type' => 'transfer',
                                'amount_cents' => rand(5000, 100000),
                                'currency_code' => $user->currency_code,
                                'occurred_at' => $date->copy()->setTime(rand(8, 22), rand(0, 59)),
                                'source_account_id' => $sourceAccount->id,
                                'target_account_id' => $targetAccount->id,
                            ]);
                            
                            $transaction->description = 'Transfer between accounts';
                            
                            $transaction->save();
                        }
                    }
                }
            }

            // Add some recurring monthly expenses
            $this->createRecurringExpenses($user, $defaultAccount, $expenseCategories, $paymentMethods);
        }
    }

    private function randomTransactionType(): string
    {
        $rand = rand(1, 100);
        
        if ($rand <= 70) {
            return 'expense';
        } elseif ($rand <= 85) {
            return 'income';
        } else {
            return 'transfer';
        }
    }

    private function getExpenseDescription(): string
    {
        $descriptions = [
            'Coffee shop',
            'Grocery shopping',
            'Gas station',
            'Restaurant dinner',
            'Online shopping',
            'Pharmacy',
            'Uber ride',
            'Movie tickets',
            'Gym membership',
            'Phone bill',
            'Internet bill',
            'Electricity bill',
            'Water bill',
            'Netflix subscription',
            'Spotify subscription',
            'Parking fee',
            'Doctor visit',
            'Haircut',
            'Clothing purchase',
            'Book purchase',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getIncomeDescription(): string
    {
        $descriptions = [
            'Monthly salary',
            'Freelance project',
            'Bonus payment',
            'Investment returns',
            'Tax refund',
            'Gift received',
            'Sales commission',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getMerchant(): ?string
    {
        $merchants = [
            'Starbucks',
            'Carrefour',
            'YPF',
            'McDonald\'s',
            'MercadoLibre',
            'Farmacity',
            'Coto',
            'Disco',
            'Shell',
            'Amazon',
            null,
        ];

        return $merchants[array_rand($merchants)];
    }

    private function getRandomTags(): ?array
    {
        if (rand(0, 10) > 7) {
            $allTags = ['work', 'personal', 'urgent', 'recurring', 'travel', 'health', 'family'];
            $numTags = rand(1, 3);
            $selectedTags = array_rand(array_flip($allTags), $numTags);
            
            return is_array($selectedTags) ? $selectedTags : [$selectedTags];
        }

        return null;
    }

    private function createRecurringExpenses(User $user, Account $account, $categories, $paymentMethods): void
    {
        $recurringExpenses = [
            ['description' => 'Rent payment', 'amount_cents' => 150000, 'day' => 1],
            ['description' => 'Netflix', 'amount_cents' => 1500, 'day' => 5],
            ['description' => 'Spotify', 'amount_cents' => 800, 'day' => 10],
            ['description' => 'Gym membership', 'amount_cents' => 8000, 'day' => 15],
        ];

        $recurrenceGroupId = \Illuminate\Support\Str::uuid();

        foreach ($recurringExpenses as $expense) {
            for ($month = 0; $month < 3; $month++) {
                $transaction = new Transaction([
                    'user_id' => $user->id,
                    'type' => 'expense',
                    'amount_cents' => $expense['amount_cents'],
                    'currency_code' => $user->currency_code,
                    'occurred_at' => now()->subMonths($month)->day($expense['day'])->setTime(10, 0),
                    'account_id' => $account->id,
                    'category_id' => $categories->random()->id,
                    'payment_method_id' => $paymentMethods->random()->id,
                    'is_recurring' => true,
                    'recurrence_group_id' => $recurrenceGroupId,
                ]);
                
                $transaction->description = $expense['description'];
                $transaction->tags = ['recurring']; // This is an array, which is correct
                
                $transaction->save();
            }
        }
    }
}