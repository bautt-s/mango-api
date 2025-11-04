<?php

namespace Database\Seeders;

use App\Models\Configurations\PaymentMethod;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Cash
            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'cash',
                'label' => 'Cash',
                'is_default' => true,
            ]);

            // Credit card
            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'credit_card',
                'label' => 'Visa',
                'issuer' => 'Banco Galicia',
                'network' => 'Visa',
                'last4' => '4532',
                'is_default' => false,
                'metadata' => [
                    'color' => '#1A1F71',
                    'credit_limit' => 500000,
                ],
            ]);

            // Debit card
            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'debit_card',
                'label' => 'Mastercard Debit',
                'issuer' => 'Banco Nación',
                'network' => 'Mastercard',
                'last4' => '8765',
                'is_default' => false,
            ]);

            // Digital wallet
            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'digital_wallet',
                'label' => 'Mercado Pago',
                'is_default' => false,
                'metadata' => [
                    'account_email' => $user->email,
                ],
            ]);

            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'digital_wallet',
                'label' => 'Ualá',
                'is_default' => false,
            ]);
        }
    }
}
