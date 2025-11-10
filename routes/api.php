<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\MainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;

Route::prefix('v1')->group(function () {

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [MainAuthController::class, 'getLoggedUser']);

        Route::post('/logout', [MainAuthController::class, 'logout']);

        Route::post('/email-verification-code', [EmailVerificationController::class, 'sendCode']);
        Route::post('/verify-email', [EmailVerificationController::class, 'verifyCode']);
    });

    Route::post('/register', [MainAuthController::class, 'register']);
    Route::post('/login', [MainAuthController::class, 'login']);
    Route::post('/send-code', [ForgotPasswordController::class, 'sendCode']);
    Route::post('/validate-code', [ForgotPasswordController::class, 'validateCode']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'changePassword']);
});
