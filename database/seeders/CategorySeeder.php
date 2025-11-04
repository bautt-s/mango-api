<?php

namespace Database\Seeders;

use App\Models\Configurations\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Expense categories
            [
                'user_id' => null,
                'name' => 'Food & Dining',
                'kind' => 'expense',
                'color' => '#FF6B6B',
                'icon' => '🍔',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Groceries',
                'kind' => 'expense',
                'color' => '#4ECDC4',
                'icon' => '🛒',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Housing',
                'kind' => 'expense',
                'color' => '#95E1D3',
                'icon' => '🏠',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Transportation',
                'kind' => 'expense',
                'color' => '#F38181',
                'icon' => '🚗',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Utilities',
                'kind' => 'expense',
                'color' => '#AA96DA',
                'icon' => '💡',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Healthcare',
                'kind' => 'expense',
                'color' => '#FCBAD3',
                'icon' => '⚕️',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Entertainment',
                'kind' => 'expense',
                'color' => '#FFFFD2',
                'icon' => '🎮',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Shopping',
                'kind' => 'expense',
                'color' => '#A8D8EA',
                'icon' => '🛍️',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Subscriptions',
                'kind' => 'expense',
                'color' => '#FFB6B9',
                'icon' => '📱',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Education',
                'kind' => 'expense',
                'color' => '#FEC8D8',
                'icon' => '📚',
                'is_system' => true,
            ],
            
            // Income categories
            [
                'user_id' => null,
                'name' => 'Salary',
                'kind' => 'income',
                'color' => '#51CF66',
                'icon' => '💰',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Freelance',
                'kind' => 'income',
                'color' => '#74C0FC',
                'icon' => '💼',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Investments',
                'kind' => 'income',
                'color' => '#FFD43B',
                'icon' => '📈',
                'is_system' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Other Income',
                'kind' => 'income',
                'color' => '#B197FC',
                'icon' => '💵',
                'is_system' => true,
            ],
            
            // Both categories
            [
                'user_id' => null,
                'name' => 'General',
                'kind' => 'both',
                'color' => '#868E96',
                'icon' => '📋',
                'is_system' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}