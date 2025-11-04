<?php

namespace Database\Seeders;

use App\Models\Personal\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('usuario1'),
            'phone' => '+5492914567890',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency_code' => 'ARS',
            'locale' => 'es-AR',
            'role' => 'admin',
            'is_premium' => true,
            'premium_since' => now(),
            'last_login_at' => now(),
        ]);

        // Premium user
        User::create([
            'name' => 'Premium User',
            'email' => 'premium@example.com',
            'password' => Hash::make('password'),
            'phone' => '+5492914567891',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency_code' => 'ARS',
            'locale' => 'es-AR',
            'role' => 'user',
            'is_premium' => true,
            'premium_since' => now()->subMonths(3),
            'last_login_at' => now()->subDays(2),
        ]);

        // Trial user
        User::create([
            'name' => 'Trial User',
            'email' => 'trial@example.com',
            'password' => Hash::make('password'),
            'phone' => '+5492914567892',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency_code' => 'ARS',
            'locale' => 'es-AR',
            'role' => 'user',
            'is_premium' => false,
            'trial_ends_at' => now()->addDays(14),
            'last_login_at' => now()->subHours(5),
        ]);

        // Regular users
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '+5492914567893',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency_code' => 'ARS',
            'locale' => 'es-AR',
            'role' => 'user',
            'is_premium' => false,
            'last_login_at' => now()->subDay(),
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'phone' => '+5492914567894',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency_code' => 'USD',
            'locale' => 'en-US',
            'role' => 'user',
            'is_premium' => false,
            'last_login_at' => now()->subDays(3),
        ]);
    }
}