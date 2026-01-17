<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\Pos\LookupController;
use App\Http\Controllers\Api\Pos\TransactionController;
use App\Http\Controllers\Api\Wallet\WalletController;
use App\Http\Controllers\Finance\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'role:kasir,admin,super_admin'])->prefix('pos')->group(function () {
    Route::get('locations', [LookupController::class, 'locations']);
    Route::get('categories', [LookupController::class, 'categories']);
    Route::get('products', [LookupController::class, 'products']);
    Route::get('santris', [LookupController::class, 'santris']);
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::post('transactions/offline-sync', [TransactionController::class, 'sync']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:kasir,admin,super_admin')->group(function () {
        Route::post('wallets/{santri}/top-up', [WalletController::class, 'topUp']);
    });

    Route::get('wallets/{santri}/transactions', [WalletController::class, 'history'])
        ->middleware('role:admin,bendahara,kasir,santri,wali,super_admin');

    // Analytics API for dashboard charts
    Route::middleware('role:admin,bendahara,super_admin')->prefix('analytics')->group(function () {
        Route::get('sales-weekly', [AnalyticsController::class, 'salesWeekly']);
        Route::get('cash-flow-weekly', [AnalyticsController::class, 'cashFlowWeekly']);
        Route::get('kpis', [AnalyticsController::class, 'kpis']);
    });
});

Route::post('payments/webhook/{provider}', PaymentWebhookController::class);
