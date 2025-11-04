<?php

namespace Database\Seeders;

use App\Models\Configurations\Budget;
use App\Models\Configurations\Category;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $expenseCategories = Category::expense()->limit(5)->get();

            // Global monthly budget
            Budget::create([
                'user_id' => $user->id,
                'category_id' => null,
                'name' => 'Monthly Budget',
                'period' => 'monthly',
                'period_start_date' => now()->startOfMonth(),
                'period_end_date' => now()->endOfMonth(),
                'amount_cents' => 500000, // 5000 currency units
                'currency_code' => $user->currency_code,
                'active' => true,
            ]);

            // Category-specific budgets
            foreach ($expenseCategories as $index => $category) {
                $amounts = [50000, 80000, 30000, 20000, 15000];

                Budget::create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'name' => $category->name . ' Budget',
                    'period' => 'monthly',
                    'period_start_date' => now()->startOfMonth(),
                    'period_end_date' => now()->endOfMonth(),
                    'amount_cents' => $amounts[$index] ?? 30000,
                    'currency_code' => $user->currency_code,
                    'active' => true,
                ]);
            }

            // Weekly budget
            Budget::create([
                'user_id' => $user->id,
                'category_id' => null,
                'name' => 'Weekly Spending',
                'period' => 'weekly',
                'period_start_date' => now()->startOfWeek(),
                'period_end_date' => now()->endOfWeek(),
                'amount_cents' => 100000,
                'currency_code' => $user->currency_code,
                'active' => true,
            ]);

            // Yearly budget
            Budget::create([
                'user_id' => $user->id,
                'category_id' => null,
                'name' => 'Annual Budget',
                'period' => 'yearly',
                'period_start_date' => now()->startOfYear(),
                'period_end_date' => now()->endOfYear(),
                'amount_cents' => 5000000,
                'currency_code' => $user->currency_code,
                'active' => true,
            ]);

            // Previous month budget (for historical data)
            Budget::create([
                'user_id' => $user->id,
                'category_id' => null,
                'name' => 'Last Month Budget',
                'period' => 'monthly',
                'period_start_date' => now()->subMonth()->startOfMonth(),
                'period_end_date' => now()->subMonth()->endOfMonth(),
                'amount_cents' => 480000,
                'currency_code' => $user->currency_code,
                'active' => false,
            ]);
        }
    }
}
