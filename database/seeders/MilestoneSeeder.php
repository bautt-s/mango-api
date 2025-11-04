<?php

namespace Database\Seeders;

use App\Models\Personal\Milestone;
use App\Models\Personal\User;
use Illuminate\Database\Seeder;

class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $milestones = [
            [
                'code' => 'first_transaction',
                'title' => 'First Transaction',
                'description' => 'Logged your first transaction',
            ],
            [
                'code' => 'first_week',
                'title' => 'One Week Strong',
                'description' => 'Used the app for one week',
            ],
            [
                'code' => 'first_month',
                'title' => 'Monthly Milestone',
                'description' => 'Used the app for one month',
            ],
            [
                'code' => 'transactions_10',
                'title' => '10 Transactions',
                'description' => 'Logged 10 transactions',
            ],
            [
                'code' => 'transactions_50',
                'title' => '50 Transactions',
                'description' => 'Logged 50 transactions',
            ],
            [
                'code' => 'transactions_100',
                'title' => 'Century Club',
                'description' => 'Logged 100 transactions',
            ],
            [
                'code' => 'budget_created',
                'title' => 'Budget Master',
                'description' => 'Created your first budget',
            ],
            [
                'code' => 'budget_met',
                'title' => 'Budget Goal Met',
                'description' => 'Successfully stayed within a budget',
            ],
            [
                'code' => 'savings_goal',
                'title' => 'Savings Champion',
                'description' => 'Reached a savings goal',
            ],
            [
                'code' => 'category_organized',
                'title' => 'Organized',
                'description' => 'Created custom categories',
            ],
            [
                'code' => 'whatsapp_first',
                'title' => 'WhatsApp Pioneer',
                'description' => 'Logged first transaction via WhatsApp',
            ],
            [
                'code' => 'daily_streak_7',
                'title' => 'Week Warrior',
                'description' => '7-day logging streak',
            ],
            [
                'code' => 'daily_streak_30',
                'title' => 'Monthly Dedication',
                'description' => '30-day logging streak',
            ],
        ];

        foreach ($users as $user) {
            foreach ($milestones as $index => $milestone) {
                // Randomly achieve some milestones for variety
                $achieved = rand(0, 100) > 50;

                Milestone::create([
                    'user_id' => $user->id,
                    'code' => $milestone['code'],
                    'title' => $milestone['title'],
                    'description' => $milestone['description'],
                    'achieved_at' => $achieved ? now()->subDays(rand(1, 60)) : null,
                ]);
            }
        }
    }
}
