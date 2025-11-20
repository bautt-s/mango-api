<?php

namespace App\Services\Personal;

use App\Models\Configurations\Budget;
use App\Models\Configurations\Category;
use App\Models\Configurations\Transaction;
use App\Models\Personal\Milestone;
use App\Models\Personal\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MilestoneService
{
    /**
     * Check all milestones for a user and return newly achieved ones
     */
    public function checkMilestones(User $user): Collection
    {
        $newlyAchieved = collect();

        // Check transaction-based milestones
        $transactionMilestones = $this->evaluateTransactionMilestones($user);
        $newlyAchieved = $newlyAchieved->merge($transactionMilestones);

        // Check streak-based milestones
        $streakMilestones = $this->evaluateStreakMilestones($user);
        $newlyAchieved = $newlyAchieved->merge($streakMilestones);

        // Check category milestones
        $categoryMilestones = $this->evaluateCategoryMilestones($user);
        $newlyAchieved = $newlyAchieved->merge($categoryMilestones);

        // Check budget milestones
        $budgetMilestones = $this->evaluateBudgetMilestones($user);
        $newlyAchieved = $newlyAchieved->merge($budgetMilestones);

        return $newlyAchieved;
    }

    /**
     * Manually achieve a milestone for a user
     */
    public function achieveMilestone(User $user, string $code): ?Milestone
    {
        $milestone = Milestone::firstOrCreate(
            [
                'user_id' => $user->id,
                'code' => $code,
            ],
            [
                'title' => $this->getMilestoneTitle($code),
                'description' => $this->getMilestoneDescription($code),
            ]
        );

        if ($milestone->isAchieved()) {
            return null; // Already achieved
        }

        $milestone->achieve();

        Log::channel('audit')->info('Milestone achieved', [
            'user_id' => $user->id,
            'milestone_code' => $code,
            'milestone_id' => $milestone->id,
        ]);

        return $milestone;
    }

    /**
     * Get milestone progress for a user
     */
    public function getMilestoneProgress(User $user): array
    {
        $allMilestoneCodes = $this->getAllMilestoneCodes();
        $userMilestones = Milestone::where('user_id', $user->id)->get()->keyBy('code');

        $progress = [];

        foreach ($allMilestoneCodes as $code) {
            $milestone = $userMilestones->get($code);

            $progress[] = [
                'code' => $code,
                'title' => $this->getMilestoneTitle($code),
                'description' => $this->getMilestoneDescription($code),
                'category' => $this->getMilestoneCategory($code),
                'is_achieved' => $milestone?->isAchieved() ?? false,
                'achieved_at' => $milestone?->reached_at?->toIso8601String(),
                'progress' => $this->calculateProgress($user, $code),
            ];
        }

        return $progress;
    }

    // ==================== Type-Specific Evaluators ====================

    /**
     * Check transaction-based milestones
     */
    public function evaluateTransactionMilestones(User $user): Collection
    {
        $transactionCount = Transaction::where('user_id', $user->id)->count();
        $newlyAchieved = collect();

        $milestonesToCheck = [
            ['code' => 'first_transaction', 'threshold' => 1],
            ['code' => 'transactions_10', 'threshold' => 10],
            ['code' => 'transactions_50', 'threshold' => 50],
            ['code' => 'transactions_100', 'threshold' => 100],
            ['code' => 'transactions_500', 'threshold' => 500],
            ['code' => 'transactions_1000', 'threshold' => 1000],
        ];

        foreach ($milestonesToCheck as $milestone) {
            if ($transactionCount >= $milestone['threshold']) {
                $achieved = $this->achieveIfNotExists($user, $milestone['code']);
                if ($achieved) {
                    $newlyAchieved->push($achieved);
                }
            }
        }

        return $newlyAchieved;
    }

    /**
     * Check streak-based milestones
     */
    public function evaluateStreakMilestones(User $user): Collection
    {
        $streak = $this->calculateCurrentStreak($user);
        $newlyAchieved = collect();

        $streakMilestones = [
            ['code' => 'daily_streak_7', 'threshold' => 7],
            ['code' => 'daily_streak_30', 'threshold' => 30],
            ['code' => 'daily_streak_90', 'threshold' => 90],
            ['code' => 'daily_streak_365', 'threshold' => 365],
        ];

        foreach ($streakMilestones as $milestone) {
            if ($streak >= $milestone['threshold']) {
                $achieved = $this->achieveIfNotExists($user, $milestone['code']);
                if ($achieved) {
                    $newlyAchieved->push($achieved);
                }
            }
        }

        return $newlyAchieved;
    }

    /**
     * Check category-related milestones
     */
    public function evaluateCategoryMilestones(User $user): Collection
    {
        $customCategoriesCount = Category::where('user_id', $user->id)->count();
        $newlyAchieved = collect();

        if ($customCategoriesCount >= 1) {
            $achieved = $this->achieveIfNotExists($user, 'category_organized');
            if ($achieved) {
                $newlyAchieved->push($achieved);
            }
        }

        if ($customCategoriesCount >= 5) {
            $achieved = $this->achieveIfNotExists($user, 'category_master');
            if ($achieved) {
                $newlyAchieved->push($achieved);
            }
        }

        return $newlyAchieved;
    }

    /**
     * Check budget-related milestones
     */
    public function evaluateBudgetMilestones(User $user): Collection
    {
        $newlyAchieved = collect();

        // First budget created
        $budgetsCount = Budget::where('user_id', $user->id)->count();
        if ($budgetsCount >= 1) {
            $achieved = $this->achieveIfNotExists($user, 'budget_created');
            if ($achieved) {
                $newlyAchieved->push($achieved);
            }
        }

        // Check if user has stayed within budget (any budget)
        $budgets = Budget::where('user_id', $user->id)->get();
        foreach ($budgets as $budget) {
            $spent = $this->calculateBudgetSpent($budget);
            if ($spent > 0 && $spent <= $budget->limit_cents) {
                $achieved = $this->achieveIfNotExists($user, 'budget_met');
                if ($achieved) {
                    $newlyAchieved->push($achieved);
                    break; // Only need one budget to be met
                }
            }
        }

        return $newlyAchieved;
    }

    // ==================== Helper Methods ====================

    /**
     * Achieve milestone if it doesn't exist or isn't achieved yet
     */
    private function achieveIfNotExists(User $user, string $code): ?Milestone
    {
        $milestone = Milestone::where('user_id', $user->id)
            ->where('code', $code)
            ->first();

        if (!$milestone) {
            return $this->achieveMilestone($user, $code);
        }

        if (!$milestone->isAchieved()) {
            $milestone->achieve();
            return $milestone;
        }

        return null; // Already achieved
    }

    /**
     * Calculate current streak of days with transactions
     */
    private function calculateCurrentStreak(User $user): int
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('occurred_at', 'desc')
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $currentDate = Carbon::now()->startOfDay();
        $lastTransactionDate = null;

        foreach ($transactions as $transaction) {
            $transactionDate = $transaction->occurred_at->startOfDay();

            if ($lastTransactionDate && $transactionDate->diffInDays($lastTransactionDate) > 1) {
                // Gap found, streak broken
                break;
            }

            if ($transactionDate->eq($currentDate->copy()->subDays($streak))) {
                $streak++;
                $lastTransactionDate = $transactionDate;
            }
        }

        return $streak;
    }

    /**
     * Calculate spent amount for a budget
     */
    private function calculateBudgetSpent(Budget $budget): int
    {
        // Simple calculation, you might want to inject BudgetService
        $query = Transaction::where('user_id', $budget->user_id)
            ->where('type', 'expense');

        if ($budget->category_id) {
            $query->where('category_id', $budget->category_id);
        }

        // Get transactions for current period
        $now = Carbon::now();
        if ($budget->period === 'monthly') {
            $query->whereYear('occurred_at', $now->year)
                ->whereMonth('occurred_at', $now->month);
        } else {
            $query->whereYear('occurred_at', $now->year);
        }

        return $query->sum('amount_cents');
    }

    /**
     * Calculate progress towards a specific milestone
     */
    private function calculateProgress(User $user, string $code): array
    {
        $progress = [
            'current' => 0,
            'target' => 0,
            'percentage' => 0,
        ];

        switch ($code) {
            case 'first_transaction':
                $progress['current'] = min(Transaction::where('user_id', $user->id)->count(), 1);
                $progress['target'] = 1;
                break;

            case 'transactions_10':
                $progress['current'] = Transaction::where('user_id', $user->id)->count();
                $progress['target'] = 10;
                break;

            case 'transactions_50':
                $progress['current'] = Transaction::where('user_id', $user->id)->count();
                $progress['target'] = 50;
                break;

            case 'transactions_100':
                $progress['current'] = Transaction::where('user_id', $user->id)->count();
                $progress['target'] = 100;
                break;

            case 'transactions_500':
                $progress['current'] = Transaction::where('user_id', $user->id)->count();
                $progress['target'] = 500;
                break;

            case 'transactions_1000':
                $progress['current'] = Transaction::where('user_id', $user->id)->count();
                $progress['target'] = 1000;
                break;

            case 'daily_streak_7':
                $progress['current'] = $this->calculateCurrentStreak($user);
                $progress['target'] = 7;
                break;

            case 'daily_streak_30':
                $progress['current'] = $this->calculateCurrentStreak($user);
                $progress['target'] = 30;
                break;

            case 'daily_streak_90':
                $progress['current'] = $this->calculateCurrentStreak($user);
                $progress['target'] = 90;
                break;

            case 'daily_streak_365':
                $progress['current'] = $this->calculateCurrentStreak($user);
                $progress['target'] = 365;
                break;

            case 'category_organized':
                $progress['current'] = Category::where('user_id', $user->id)->count();
                $progress['target'] = 1;
                break;

            case 'category_master':
                $progress['current'] = Category::where('user_id', $user->id)->count();
                $progress['target'] = 5;
                break;

            case 'budget_created':
                $progress['current'] = min(Budget::where('user_id', $user->id)->count(), 1);
                $progress['target'] = 1;
                break;

            case 'budget_met':
                // Check if any budget is currently being met
                $budgets = Budget::where('user_id', $user->id)->get();
                $metCount = 0;
                foreach ($budgets as $budget) {
                    $spent = $this->calculateBudgetSpent($budget);
                    if ($spent > 0 && $spent <= $budget->limit_cents) {
                        $metCount = 1;
                        break;
                    }
                }
                $progress['current'] = $metCount;
                $progress['target'] = 1;
                break;
        }

        $progress['percentage'] = $progress['target'] > 0
            ? min(100, round(($progress['current'] / $progress['target']) * 100, 2))
            : 0;

        return $progress;
    }

    /**
     * Get all available milestone codes
     */
    private function getAllMilestoneCodes(): array
    {
        return [
            // Transaction milestones
            'first_transaction',
            'transactions_10',
            'transactions_50',
            'transactions_100',
            'transactions_500',
            'transactions_1000',

            // Streak milestones
            'daily_streak_7',
            'daily_streak_30',
            'daily_streak_90',
            'daily_streak_365',

            // Category milestones
            'category_organized',
            'category_master',

            // Budget milestones
            'budget_created',
            'budget_met',

            // Special milestones
            'savings_goal',
            'whatsapp_first',
        ];
    }

    /**
     * Get milestone title by code
     */
    private function getMilestoneTitle(string $code): string
    {
        $titles = [
            'first_transaction' => 'Primera Transacción',
            'transactions_10' => '10 Transacciones',
            'transactions_50' => '50 Transacciones',
            'transactions_100' => 'Club de los 100',
            'transactions_500' => '500 Transacciones',
            'transactions_1000' => '¡1000 Transacciones!',
            'daily_streak_7' => 'Racha de 7 Días',
            'daily_streak_30' => 'Racha de 30 Días',
            'daily_streak_90' => 'Racha de 90 Días',
            'daily_streak_365' => '¡Racha de 1 Año!',
            'category_organized' => 'Organizado',
            'category_master' => 'Maestro de Categorías',
            'budget_created' => 'Maestro del Presupuesto',
            'budget_met' => 'Meta de Presupuesto Cumplida',
            'savings_goal' => 'Campeón del Ahorro',
            'whatsapp_first' => 'Pionero de WhatsApp',
        ];

        return $titles[$code] ?? $code;
    }

    /**
     * Get milestone description by code
     */
    private function getMilestoneDescription(string $code): string
    {
        $descriptions = [
            'first_transaction' => 'Registraste tu primera transacción',
            'transactions_10' => 'Has registrado 10 transacciones',
            'transactions_50' => 'Has registrado 50 transacciones',
            'transactions_100' => 'Has registrado 100 transacciones',
            'transactions_500' => 'Has registrado 500 transacciones',
            'transactions_1000' => 'Has registrado 1000 transacciones',
            'daily_streak_7' => 'Registraste transacciones durante 7 días consecutivos',
            'daily_streak_30' => 'Registraste transacciones durante 30 días consecutivos',
            'daily_streak_90' => 'Registraste transacciones durante 90 días consecutivos',
            'daily_streak_365' => 'Registraste transacciones durante 365 días consecutivos',
            'category_organized' => 'Creaste tu primera categoría personalizada',
            'category_master' => 'Creaste 5 categorías personalizadas',
            'budget_created' => 'Creaste tu primer presupuesto',
            'budget_met' => 'Te mantuviste dentro de tu presupuesto',
            'savings_goal' => 'Alcanzaste tu meta de ahorro',
            'whatsapp_first' => 'Registraste tu primera transacción vía WhatsApp',
        ];

        return $descriptions[$code] ?? 'Logro desbloqueado';
    }

    /**
     * Get milestone category
     */
    private function getMilestoneCategory(string $code): string
    {
        if (str_starts_with($code, 'transaction')) {
            return 'transactions';
        }
        if (str_starts_with($code, 'daily_streak')) {
            return 'streaks';
        }
        if (str_starts_with($code, 'category')) {
            return 'categories';
        }
        if (str_starts_with($code, 'budget')) {
            return 'budgets';
        }
        return 'special';
    }
}