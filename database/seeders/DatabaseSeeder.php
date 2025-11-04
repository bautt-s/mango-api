<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            ExchangeRateSeeder::class,
            PaymentMethodSeeder::class,
            AccountSeeder::class,
            TransactionSeeder::class,
            BudgetSeeder::class,
            MilestoneSeeder::class,
        ]);
    }
}