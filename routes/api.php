<?php

use App\Http\Controllers\Alerts\AlertController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\MainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Configurations\AccountController;
use App\Http\Controllers\Configurations\BudgetController;
use App\Http\Controllers\Configurations\CategoryController;
use App\Http\Controllers\Configurations\PaymentMethodController;
use App\Http\Controllers\Configurations\TransactionController;
use App\Http\Controllers\Personal\UserController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [MainAuthController::class, 'register']);
    Route::post('/login', [MainAuthController::class, 'login']);
    Route::post('/send-code', [ForgotPasswordController::class, 'sendCode']);
    Route::post('/validate-code', [ForgotPasswordController::class, 'validateCode']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'changePassword']);

    Route::middleware('auth:sanctum')->group(function () {
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

        Route::prefix('accounts')->group(function () {
            Route::get('/', [AccountController::class, 'index']);
            Route::post('/', [AccountController::class, 'store']);
            Route::put('/{account}', [AccountController::class, 'update']);
            Route::patch('/{account}/archive', [AccountController::class, 'archive']);
            Route::patch('/{account}/unarchive', [AccountController::class, 'unarchive']);
            Route::patch('/{account}/default', [AccountController::class, 'setDefault']);
            Route::patch('/reorder', [AccountController::class, 'reorder']);
        });

        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::get('/roots', [CategoryController::class, 'roots']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
            Route::get('/{category}/stats', [CategoryController::class, 'stats']);
        });

        Route::prefix('payment-methods')->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index']);
            Route::post('/', [PaymentMethodController::class, 'store']);
            Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update']);
            Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
            Route::patch('/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault']);
            Route::patch('/{paymentMethod}/billing-cycle', [PaymentMethodController::class, 'setBillingCycle']);
        });

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

        Route::prefix('budgets')->group(function () {
            Route::get('/', [BudgetController::class, 'index']);
            Route::post('/', [BudgetController::class, 'store']);
            Route::get('/current', [BudgetController::class, 'current']);
            Route::get('/{budget}', [BudgetController::class, 'show']);
            Route::put('/{budget}', [BudgetController::class, 'update']);
            Route::delete('/{budget}', [BudgetController::class, 'destroy']);
            Route::patch('/{budget}/rollover', [BudgetController::class, 'toggleRollover']);
        });

        Route::prefix('alerts')->group(function () {
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
    });
});