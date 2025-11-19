<?php

namespace Database\Seeders;

use App\Models\Configurations\Account;
use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertPreference;
use App\Models\Configurations\Budget;
use App\Models\Configurations\PaymentMethod;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Create alert preferences for each user
            AlertPreference::create([
                'user_id' => $user->id,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'in_app_enabled' => true,
                'quiet_hours' => [
                    'enabled' => true,
                    'from' => '22:00',
                    'to' => '08:00',
                    'timezone' => $user->timezone,
                ],
                'active_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'enable_digest' => false,
            ]);

            // Get user's budgets, accounts, and payment methods
            $budgets = Budget::where('user_id', $user->id)->get();
            $accounts = Account::where('user_id', $user->id)->get();
            $paymentMethods = PaymentMethod::where('user_id', $user->id)->get();

            // Create budget threshold alerts
            foreach ($budgets->take(2) as $budget) {
                Alert::create([
                    'user_id' => $user->id,
                    'type' => 'budget_threshold',
                    'name' => "Alerta 80% - {$budget->name}",
                    'description' => "Notificar cuando se alcance el 80% del presupuesto '{$budget->name}'",
                    'conditions' => [
                        'budget_id' => $budget->id,
                        'threshold_percentage' => 80,
                    ],
                    'active' => true,
                    'frequency' => 'daily',
                ]);

                Alert::create([
                    'user_id' => $user->id,
                    'type' => 'budget_exceeded',
                    'name' => "Presupuesto Excedido - {$budget->name}",
                    'description' => "Notificar cuando se exceda el presupuesto '{$budget->name}'",
                    'conditions' => [
                        'budget_id' => $budget->id,
                    ],
                    'active' => true,
                    'frequency' => 'every_time',
                ]);
            }

            // Create low balance alert for first account
            if ($accounts->isNotEmpty()) {
                $account = $accounts->first();
                Alert::create([
                    'user_id' => $user->id,
                    'type' => 'low_balance',
                    'name' => "Saldo Bajo - {$account->label}",
                    'description' => "Notificar cuando el saldo de '{$account->label}' esté bajo",
                    'conditions' => [
                        'account_id' => $account->id,
                        'threshold_cents' => 50000, // 500 ARS
                    ],
                    'active' => true,
                    'frequency' => 'daily',
                ]);
            }

            // Create payment due alert for credit card
            $creditCard = $paymentMethods->where('type', 'credit_card')->first();
            if ($creditCard && isset($creditCard->metadata['billing_day'])) {
                Alert::create([
                    'user_id' => $user->id,
                    'type' => 'payment_due',
                    'name' => "Vencimiento - {$creditCard->label}",
                    'description' => "Recordatorio de vencimiento de tarjeta '{$creditCard->label}'",
                    'conditions' => [
                        'payment_method_id' => $creditCard->id,
                        'days_before' => 3,
                    ],
                    'active' => true,
                    'frequency' => 'monthly',
                ]);
            }

            // Create one inactive alert
            Alert::create([
                'user_id' => $user->id,
                'type' => 'unusual_spending',
                'name' => 'Gasto Inusual Detectado',
                'description' => 'Notificar cuando se detecte un gasto inusualmente alto',
                'conditions' => [
                    'threshold_multiplier' => 2.0, // 2x the average
                ],
                'active' => false,
                'frequency' => 'every_time',
            ]);
        }

        $this->command->info('Alert seeding completed!');
        $this->command->info('- Created alert preferences for all users');
        $this->command->info('- Created budget threshold alerts');
        $this->command->info('- Created budget exceeded alerts');
        $this->command->info('- Created low balance alerts');
        $this->command->info('- Created payment due alerts');
    }
}