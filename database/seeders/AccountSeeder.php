<?php

namespace Database\Seeders;

use App\Models\Configurations\Account;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Main checking account
            Account::create([
                'user_id' => $user->id,
                'label' => 'Checking Account',
                'color' => '#3B82F6',
                'currency_code' => $user->currency_code,
                'is_default' => true,
                'archived' => false,
                'sort_order' => 1,
                'metadata' => [
                    'account_number' => '****' . rand(1000, 9999),
                    'bank' => 'Banco Galicia',
                ],
            ]);

            // Savings account
            Account::create([
                'user_id' => $user->id,
                'label' => 'Savings',
                'color' => '#10B981',
                'currency_code' => $user->currency_code,
                'is_default' => false,
                'archived' => false,
                'sort_order' => 2,
                'metadata' => [
                    'account_number' => '****' . rand(1000, 9999),
                    'bank' => 'Banco Nación',
                    'interest_rate' => 45.0,
                ],
            ]);

            // Cash wallet
            Account::create([
                'user_id' => $user->id,
                'label' => 'Cash Wallet',
                'color' => '#F59E0B',
                'currency_code' => $user->currency_code,
                'is_default' => false,
                'archived' => false,
                'sort_order' => 3,
            ]);

            // Digital wallet
            Account::create([
                'user_id' => $user->id,
                'label' => 'Mercado Pago',
                'color' => '#06B6D4',
                'currency_code' => $user->currency_code,
                'is_default' => false,
                'archived' => false,
                'sort_order' => 4,
                'metadata' => [
                    'wallet_type' => 'mercadopago',
                ],
            ]);

            // USD savings (if user is ARS based)
            if ($user->currency_code === 'ARS') {
                Account::create([
                    'user_id' => $user->id,
                    'label' => 'USD Savings',
                    'color' => '#8B5CF6',
                    'currency_code' => 'USD',
                    'is_default' => false,
                    'archived' => false,
                    'sort_order' => 5,
                    'metadata' => [
                        'bank' => 'Banco Galicia',
                        'account_type' => 'foreign_currency',
                    ],
                ]);
            }
        }
    }
}
