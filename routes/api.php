<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\MainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Configurations\AccountController;
use App\Http\Controllers\Personal\UserController;

Route::prefix('v1')->group(function () {

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
    });

    Route::post('/register', [MainAuthController::class, 'register']);
    Route::post('/login', [MainAuthController::class, 'login']);
    Route::post('/send-code', [ForgotPasswordController::class, 'sendCode']);
    Route::post('/validate-code', [ForgotPasswordController::class, 'validateCode']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'changePassword']);
});
