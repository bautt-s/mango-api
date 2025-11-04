<?php

namespace Database\Seeders;

use App\Models\Subscriptions\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free Plan',
                'interval' => 'monthly',
                'price_cents' => 0,
                'currency_code' => 'USD',
                'active' => true,
            ],
            [
                'code' => 'premium_monthly',
                'name' => 'Premium Monthly',
                'interval' => 'monthly',
                'price_cents' => 250, // $2.50
                'currency_code' => 'USD',
                'active' => true,
            ],
            [
                'code' => 'premium_annual',
                'name' => 'Premium Annual',
                'interval' => 'annual',
                'price_cents' => 2400, // $24.00
                'currency_code' => 'USD',
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
