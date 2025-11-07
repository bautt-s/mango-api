<?php

namespace Database\Seeders;

use App\Models\System\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->toDateString();
        
        $rates = [
            // USD to other currencies
            ['base_currency' => 'USD', 'target_currency' => 'ARS', 'rate' => 1050.00, 'rate_date' => $today],
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.92, 'rate_date' => $today],
            
            // EUR to other currencies
            ['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 1.09, 'rate_date' => $today],
            ['base_currency' => 'EUR', 'target_currency' => 'ARS', 'rate' => 1144.50, 'rate_date' => $today],
            
            // ARS to other currencies
            ['base_currency' => 'ARS', 'target_currency' => 'USD', 'rate' => 0.00095, 'rate_date' => $today],
            ['base_currency' => 'ARS', 'target_currency' => 'EUR', 'rate' => 0.00087, 'rate_date' => $today],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::create($rate);
        }

        // Historical rates (last 30 days with slight variations)
        for ($i = 1; $i <= 30; $i++) {
            $date = now()->subDays($i)->toDateString();
            $variation = 1 + (rand(-5, 5) / 100); // ±5% variation
            
            ExchangeRate::create([
                'base_currency' => 'USD',
                'target_currency' => 'ARS',
                'rate' => round(1050.00 * $variation, 2),
                'rate_date' => $date,
            ]);
            
            ExchangeRate::create([
                'base_currency' => 'USD',
                'target_currency' => 'EUR',
                'rate' => round(0.92 * $variation, 8),
                'rate_date' => $date,
            ]);
        }
    }
}