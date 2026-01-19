<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SantriController;
use App\Http\Controllers\Admin\SsoSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WalletManagementController;
use App\Http\Controllers\Admin\WaliController;
use App\Http\Controllers\Admin\PaymentSettingController;
use App\Http\Controllers\Admin\EmailSettingController;
use App\Http\Controllers\Admin\BrandingSettingController;
use App\Http\Controllers\Admin\AccountingSettingController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Auth\AuthBridgeController;
use App\Http\Controllers\Api\Auth\SsoController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Finance\DashboardController as FinanceDashboardController;
use App\Http\Controllers\Finance\AnalyticsController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\Finance\ReportExportController;
use App\Http\Controllers\MidtransRedirectController;
use App\Http\Controllers\PaymentRedirectController;
use App\Http\Controllers\Portal\GuardianCategoryController;
use App\Http\Controllers\Portal\GuardianPaymentController;
use App\Http\Controllers\Portal\GuardianSantriController;
use App\Http\Controllers\Portal\SantriPortalController;
use App\Http\Controllers\Portal\WaliPortalController;
use App\Http\Controllers\Portal\WalletTopupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('session.role:kasir,super_admin')->get('/pos', fn () => view('pos'))->name('pos');

Route::get('/login', fn () => view('auth.login'))->name('auth.login');
Route::post('/login', [AuthBridgeController::class, 'login'])->name('auth.login.submit');
Route::get('/logout', [AuthBridgeController::class, 'logout']);
Route::post('/logout', [AuthBridgeController::class, 'logout'])->name('auth.logout');
Route::get('/sso/login', [SsoController::class, 'redirect'])->name('sso.login');
Route::get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

Route::get('/produk', [CatalogController::class, 'index'])->name('catalog.index');

Route::get('/payments/midtrans/redirect', MidtransRedirectController::class)->name('payments.midtrans.redirect');

Route::middleware('session.role:bendahara,super_admin')->group(function () {
    Route::get('/dashboard/bendahara', FinanceDashboardController::class)->name('dashboard.finance');
    Route::get('/laporan/laba-rugi', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/laporan/neraca', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/laporan/arus-kas', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    
    // Export routes
    Route::get('/laporan/laba-rugi/pdf', [ReportExportController::class, 'profitLossPdf'])->name('reports.profit-loss.pdf');
    Route::get('/laporan/neraca/pdf', [ReportExportController::class, 'balanceSheetPdf'])->name('reports.balance-sheet.pdf');
    Route::get('/laporan/arus-kas/pdf', [ReportExportController::class, 'cashFlowPdf'])->name('reports.cash-flow.pdf');
    Route::get('/laporan/export-excel', [ReportExportController::class, 'exportExcel'])->name('reports.export-excel');
});

Route::middleware('session.role:admin,bendahara,super_admin')
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->group(function () {
        Route::get('/accounting', [AccountingSettingController::class, 'edit'])->name('accounting');
        Route::put('/accounting', [AccountingSettingController::class, 'update'])->name('accounting.update');
    });

Route::middleware('session.role:admin,bendahara,super_admin')
    ->get('/analytics/revenue-series', [AnalyticsController::class, 'revenueSeries'])
    ->name('analytics.revenue-series');

Route::middleware('session.role:admin,bendahara,kasir,wali,santri,super_admin')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});
Route::middleware('session.role:wali,super_admin')
    ->group(function () {
        Route::get('/portal/wali', WaliPortalController::class)->name('portal.wali');
        Route::post('/portal/wali/santri/{santri}/limits', [GuardianSantriController::class, 'updateLimits'])->name('portal.wali.limits');
        Route::post('/portal/wali/santri/{santri}/topup', [WalletTopupController::class, 'store'])->name('portal.wali.topup');
        Route::post('/portal/wali/santri/{santri}/categories', [GuardianCategoryController::class, 'update'])->name('portal.wali.categories');
        Route::post('/portal/wali/payments/{payment}/refresh', [GuardianPaymentController::class, 'refresh'])->name('portal.wali.payments.refresh');
    });
Route::middleware('session.role:santri,super_admin')->get('/portal/santri', SantriPortalController::class)->name('portal.santri');

Route::middleware('session.role:super_admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings/sso', [SsoSettingController::class, 'edit'])->name('settings.sso');
    Route::put('/settings/sso', [SsoSettingController::class, 'update'])->name('settings.sso.update');

    // Payment Settings
    Route::get('/settings/payments', [PaymentSettingController::class, 'index'])->name('settings.payments');
    Route::get('/settings/payments/midtrans/checklist', [PaymentSettingController::class, 'midtransChecklist'])->name('settings.payments.midtrans.checklist');
    Route::get('/settings/payments/{provider}', [PaymentSettingController::class, 'edit'])->name('settings.payments.edit');
    Route::put('/settings/payments/{provider}', [PaymentSettingController::class, 'update'])->name('settings.payments.update');
    Route::post('/settings/payments/{provider}/toggle', [PaymentSettingController::class, 'toggleActive'])->name('settings.payments.toggle');
    Route::post('/settings/payments/{provider}/priority', [PaymentSettingController::class, 'updatePriority'])->name('settings.payments.priority');

    // Email Settings
    Route::get('/settings/email', [EmailSettingController::class, 'edit'])->name('settings.email');
    Route::put('/settings/email', [EmailSettingController::class, 'update'])->name('settings.email.update');
    Route::post('/settings/email/test', [EmailSettingController::class, 'testEmail'])->name('settings.email.test');

    // Activity Logs
    Route::get('/activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs');
});

Route::middleware('session.role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('locations', LocationController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('santri', SantriController::class)->except(['show', 'destroy']);
    Route::resource('users', UserController::class)->except(['show']);

    // Wallet Management
    Route::prefix('wallets')->name('wallets.')->group(function () {
        Route::get('/', [WalletManagementController::class, 'index'])->name('index');
        Route::get('/{santri}', [WalletManagementController::class, 'show'])->name('show');
        Route::get('/{santri}/topup', [WalletManagementController::class, 'topUp'])->name('topup');
        Route::post('/{santri}/topup', [WalletManagementController::class, 'storeTopUp'])->name('topup.store');
        Route::post('/{santri}/adjust', [WalletManagementController::class, 'adjustBalance'])->name('adjust');
        Route::post('/{santri}/toggle-lock', [WalletManagementController::class, 'toggleLock'])->name('toggle-lock');
    });

    // Wali Management
    Route::resource('wali', WaliController::class)->except(['show']);

    // Branding Settings
    Route::get('/settings/branding', [BrandingSettingController::class, 'edit'])->name('settings.branding');
    Route::put('/settings/branding', [BrandingSettingController::class, 'update'])->name('settings.branding.update');

    // Accounting Settings
    // (Moved to shared admin+bendahara group above)

    // Report Center
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('index');
        Route::get('/transactions', [AdminReportController::class, 'transactionJournal'])->name('transactions');
        Route::get('/sales', [AdminReportController::class, 'salesReport'])->name('sales');
        Route::get('/wallet', [AdminReportController::class, 'walletReport'])->name('wallet');
    });

});

Route::get('/payments/redirect/{payment}', PaymentRedirectController::class)->name('payments.redirect');
