<?php

use App\Http\Controllers\Alerts\AlertController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\MainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Configurations\AccountController;
use App\Http\Controllers\Configurations\BudgetController;
use App\Http\Controllers\Configurations\CategoryController;
use App\Http\Controllers\Configurations\DailySummaryController;
use App\Http\Controllers\Configurations\PaymentMethodController;
use App\Http\Controllers\Configurations\TransactionController;
use App\Http\Controllers\Features\FeatureController;
use App\Http\Controllers\Personal\MilestoneController;
use App\Http\Controllers\Personal\UserController;
use App\Http\Controllers\Subscriptions\SubscriptionController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/register', [MainAuthController::class, 'register']);
    Route::post('/login', [MainAuthController::class, 'login']);
    Route::post('/send-code', [ForgotPasswordController::class, 'sendCode']);
    Route::post('/validate-code', [ForgotPasswordController::class, 'validateCode']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'changePassword']);

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth & User Management
        Route::get('/user', [MainAuthController::class, 'getLoggedUser']);
        Route::post('/logout', [MainAuthController::class, 'logout']);
        Route::post('/email-verification-code', [EmailVerificationController::class, 'sendCode']);
        Route::post('/verify-email', [EmailVerificationController::class, 'verifyCode']);

        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
            Route::patch('/email', [UserController::class, 'updateEmail']);
            Route::get('/subscription', [UserController::class, 'subscription']);
            Route::delete('/account', [UserController::class, 'deleteAccount']);
        });

        // FREE FEATURES - No additional middleware required

        // Accounts
        Route::prefix('accounts')->group(function () {
            Route::get('/', [AccountController::class, 'index']);
            Route::post('/', [AccountController::class, 'store']);
            Route::put('/{account}', [AccountController::class, 'update']);
            Route::patch('/{account}/archive', [AccountController::class, 'archive']);
            Route::patch('/{account}/unarchive', [AccountController::class, 'unarchive']);
            Route::patch('/{account}/default', [AccountController::class, 'setDefault']);
            Route::patch('/reorder', [AccountController::class, 'reorder']);
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::get('/roots', [CategoryController::class, 'roots']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::get('/{category}/stats', [CategoryController::class, 'stats']);
        });

        // Payment Methods
        Route::prefix('payment-methods')->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index']);
            Route::post('/', [PaymentMethodController::class, 'store']);
            Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update']);
            Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
            Route::patch('/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault']);
            Route::patch('/{paymentMethod}/billing-cycle', [PaymentMethodController::class, 'setBillingCycle']);
        });

        // Transactions
        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::post('/expense', [TransactionController::class, 'storeExpense']);
            Route::post('/income', [TransactionController::class, 'storeIncome']);
            Route::post('/transfer', [TransactionController::class, 'storeTransfer']);
            Route::put('/{transaction}', [TransactionController::class, 'update']);
            Route::delete('/{transaction}', [TransactionController::class, 'destroy']);
            Route::patch('/{transaction}/tags', [TransactionController::class, 'updateTags']);
            Route::get('/recurring-groups', [TransactionController::class, 'recurringGroups']);
        });

        // PREMIUM FEATURES - Protected by feature gating middleware

        // Budgets (Premium Feature)
        Route::middleware('feature.access:budgeting_system')->prefix('budgets')->group(function () {
            Route::get('/', [BudgetController::class, 'index']);
            Route::post('/', [BudgetController::class, 'store']);
            Route::get('/current', [BudgetController::class, 'current']);
            Route::get('/{budget}', [BudgetController::class, 'show']);
            Route::put('/{budget}', [BudgetController::class, 'update']);
            Route::delete('/{budget}', [BudgetController::class, 'destroy']);
            Route::patch('/{budget}/rollover', [BudgetController::class, 'toggleRollover']);
        });

        // Alerts (Premium Feature)
        Route::middleware('feature.access:alerts_system')->prefix('alerts')->group(function () {
            Route::get('/types', [AlertController::class, 'getAlertTypes']);
            Route::get('/preferences', [AlertController::class, 'getPreferences']);
            Route::put('/preferences', [AlertController::class, 'updatePreferences']);
            Route::get('/notifications/unread', [AlertController::class, 'getUnreadNotifications']);
            Route::patch('/notifications/{notification}/read', [AlertController::class, 'markNotificationAsRead']);
            Route::get('/', [AlertController::class, 'index']);
            Route::post('/', [AlertController::class, 'store']);
            Route::get('/{alert}', [AlertController::class, 'show']);
            Route::put('/{alert}', [AlertController::class, 'update']);
            Route::delete('/{alert}', [AlertController::class, 'destroy']);
            Route::patch('/{alert}/activate', [AlertController::class, 'activate']);
            Route::patch('/{alert}/deactivate', [AlertController::class, 'deactivate']);
            Route::post('/{alert}/snooze', [AlertController::class, 'snooze']);
            Route::post('/{alert}/unsnooze', [AlertController::class, 'unsnooze']);
            Route::post('/{alert}/test', [AlertController::class, 'test']);
        });

        // Daily Summaries (Free - all authenticated users can view their summaries)
        Route::prefix('summaries')->group(function () {
            Route::get('/', [DailySummaryController::class, 'index']);
            Route::post('/generate', [DailySummaryController::class, 'generate']);
            Route::get('/stats', [DailySummaryController::class, 'stats']);
            Route::get('/preview', [DailySummaryController::class, 'preview']);
            Route::get('/week', [DailySummaryController::class, 'week']);
            Route::get('/month', [DailySummaryController::class, 'month']);
            Route::get('/{date}', [DailySummaryController::class, 'show']);
        });

        // Feature Information (Free - users can see available features)
        Route::prefix('features')->group(function () {
            Route::get('/', [FeatureController::class, 'index']);
            Route::get('/quotas', [FeatureController::class, 'quotas']);
            Route::get('/plans', [FeatureController::class, 'plans']);
            Route::get('/{slug}/check', [FeatureController::class, 'check']);
        });

        // Milestones (Free - gamification for all users)
        Route::prefix('milestones')->group(function () {
            Route::get('/', [MilestoneController::class, 'index']);
            Route::get('/progress', [MilestoneController::class, 'progress']);
            Route::get('/recent', [MilestoneController::class, 'recent']);
            Route::get('/stats', [MilestoneController::class, 'stats']);
            Route::post('/check', [MilestoneController::class, 'check']);
            Route::get('/{milestone}', [MilestoneController::class, 'show']);
        });

        // Subscriptions
        Route::prefix('subscription')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::delete('/', [SubscriptionController::class, 'destroy']);
            //Route::post('/trial', [SubscriptionController::class, 'startTrial']);
            Route::post('/resume', [SubscriptionController::class, 'resume']);
            Route::put('/plan', [SubscriptionController::class, 'changePlan']);
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::get('/payments', [SubscriptionController::class, 'payments']);
            Route::get('/status', [SubscriptionController::class, 'status']);
        });
    });
});
