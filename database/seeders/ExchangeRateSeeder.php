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
            ['base_code' => 'USD', 'quote_code' => 'ARS', 'rate' => 1050.00, 'as_of_date' => $today],
            ['base_code' => 'USD', 'quote_code' => 'EUR', 'rate' => 0.92, 'as_of_date' => $today],
            
            // EUR to other currencies
            ['base_code' => 'EUR', 'quote_code' => 'USD', 'rate' => 1.09, 'as_of_date' => $today],
            ['base_code' => 'EUR', 'quote_code' => 'ARS', 'rate' => 1144.50, 'as_of_date' => $today],
            
            // ARS to other currencies
            ['base_code' => 'ARS', 'quote_code' => 'USD', 'rate' => 0.00095, 'as_of_date' => $today],
            ['base_code' => 'ARS', 'quote_code' => 'EUR', 'rate' => 0.00087, 'as_of_date' => $today],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::create($rate);
        }

        // Historical rates (last 30 days with slight variations)
        for ($i = 1; $i <= 30; $i++) {
            $date = now()->subDays($i)->toDateString();
            $variation = 1 + (rand(-5, 5) / 100); // ±5% variation
            
            ExchangeRate::create([
                'base_code' => 'USD',
                'quote_code' => 'ARS',
                'rate' => round(1050.00 * $variation, 2),
                'as_of_date' => $date,
            ]);
            
            ExchangeRate::create([
                'base_code' => 'USD',
                'quote_code' => 'EUR',
                'rate' => round(0.92 * $variation, 8),
                'as_of_date' => $date,
            ]);
        }
    }
}