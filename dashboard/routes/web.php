<?php

use App\Http\Controllers\Admin\BillingMonitoringController;
use App\Http\Controllers\Admin\DashboardPopupController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\RedeemCodeController as AdminRedeemCodeController;
use App\Http\Controllers\Admin\RequestLogController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\RedeemCodeController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::domain('admin.azkia.cloud')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'loginForm'])->name('admin.login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.store');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('admin.logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::get('/providers', [AdminController::class, 'providers'])->name('admin.providers');
        Route::get('/models', [AdminController::class, 'models'])->name('admin.models');
        Route::get('/pricing', [AdminController::class, 'pricing'])->name('admin.pricing');
        Route::get('/status', [AdminController::class, 'status'])->name('admin.status');
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('admin.plans.index');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('admin.plans.store');
        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])->name('admin.plans.update');
        Route::patch('/plans/{plan}/toggle', [AdminPlanController::class, 'toggle'])->name('admin.plans.toggle');
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('admin.plans.destroy');
        Route::get('/api-keys', [AdminController::class, 'keys'])->name('admin.keys');
        Route::get('/settings/payments', [PaymentSettingController::class, 'edit'])->name('admin.payment-settings.edit');
        Route::patch('/settings/payments', [PaymentSettingController::class, 'update'])->name('admin.payment-settings.update');
        Route::get('/dashboard-popups', [DashboardPopupController::class, 'index'])->name('admin.dashboard-popups.index');
        Route::post('/dashboard-popups', [DashboardPopupController::class, 'store'])->name('admin.dashboard-popups.store');
        Route::patch('/dashboard-popups/{dashboardPopup}', [DashboardPopupController::class, 'update'])->name('admin.dashboard-popups.update');
        Route::patch('/dashboard-popups/{dashboardPopup}/toggle', [DashboardPopupController::class, 'toggle'])->name('admin.dashboard-popups.toggle');
        Route::delete('/dashboard-popups/{dashboardPopup}', [DashboardPopupController::class, 'destroy'])->name('admin.dashboard-popups.destroy');
        Route::get('/redeem-codes', [AdminRedeemCodeController::class, 'index'])->name('admin.redeem-codes.index');
        Route::post('/redeem-codes', [AdminRedeemCodeController::class, 'store'])->middleware('throttle:5,1')->name('admin.redeem-codes.store');
        Route::patch('/redeem-code-batches/{batch}/disable', [AdminRedeemCodeController::class, 'disableBatch'])->name('admin.redeem-codes.batches.disable');
        Route::patch('/redeem-codes/{code}/disable', [AdminRedeemCodeController::class, 'disableCode'])->name('admin.redeem-codes.codes.disable');
        Route::get('/deposits', [DepositController::class, 'index'])->name('admin.deposits.index');
        Route::get('/deposits/export', [DepositController::class, 'export'])->middleware('throttle:10,1')->name('admin.deposits.export');
        Route::post('/deposits/reconcile-batch', [DepositController::class, 'reconcileBatch'])->middleware('throttle:2,1')->name('admin.deposits.reconcile-batch');
        Route::post('/deposits/manual-credit', [DepositController::class, 'manualCredit'])->middleware('throttle:5,1')->name('admin.deposits.manual-credit');
        Route::get('/deposits/{paymentOrder}', [DepositController::class, 'show'])->name('admin.deposits.show');
        Route::post('/deposits/{paymentOrder}/reconcile', [DepositController::class, 'reconcile'])->middleware('throttle:10,1')->name('admin.deposits.reconcile');
        Route::get('/billing-monitoring', [BillingMonitoringController::class, 'index'])->name('admin.billing-monitoring.index');
        Route::get('/billing-monitoring/{billingEvent}', [BillingMonitoringController::class, 'show'])->name('admin.billing-monitoring.show');
        Route::get('/request-logs', [RequestLogController::class, 'index'])->name('admin.request-logs.index');
        Route::get('/request-logs/{usageLog}', [RequestLogController::class, 'show'])->name('admin.request-logs.show');
        Route::get('/rejections', [AdminController::class, 'rejections'])->name('admin.rejections');
        Route::get('/support', [AdminSupportController::class, 'index'])->name('admin.support.index');
        Route::get('/support/{supportTicket}', [AdminSupportController::class, 'show'])->name('admin.support.show');
        Route::post('/support/{supportTicket}/reply', [AdminSupportController::class, 'reply'])->middleware('throttle:30,1')->name('admin.support.reply');
        Route::get('/support/{supportTicket}/attachments/{supportAttachment}', [AdminSupportController::class, 'attachment'])->name('admin.support.attachments.show');
        Route::patch('/support/{supportTicket}', [AdminSupportController::class, 'update'])->name('admin.support.update');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
        Route::get('/users/{user}', [AdminController::class, 'userDetail'])->name('admin.users.show');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::post('/providers', [AdminController::class, 'storeProvider'])->name('admin.providers.store');
        Route::patch('/providers/{provider}/toggle', [AdminController::class, 'toggleProvider'])->name('admin.providers.toggle');
        Route::post('/models', [AdminController::class, 'storeModel'])->name('admin.models.store');
        Route::get('/models/{model}/edit', [AdminController::class, 'editModel'])->name('admin.models.edit');
        Route::patch('/models/{model}', [AdminController::class, 'updateModel'])->name('admin.models.update');
        Route::post('/models/{model}/pricing', [AdminController::class, 'updateModelPricing'])->name('admin.models.pricing');
        Route::patch('/models/{model}/toggle', [AdminController::class, 'toggleModel'])->name('admin.models.toggle');
        Route::delete('/models/{model}', [AdminController::class, 'destroyModel'])->name('admin.models.destroy');
        Route::post('/pricing', [AdminController::class, 'storePricing'])->name('admin.pricing.store');
        Route::patch('/api-keys/{apiKey}', [AdminController::class, 'updateApiKey'])->name('admin.api-keys.update');
        Route::patch('/api-keys/{apiKey}/toggle', [AdminController::class, 'toggleApiKey'])->name('admin.api-keys.toggle');
        Route::post('/users/{user}/topup', [AdminController::class, 'topupUser'])->name('admin.users.topup');
        Route::post('/users/{user}/messages', [AdminController::class, 'sendInboxMessage'])->name('admin.users.messages.store');
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('admin.users.status');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    // Registrasi email/password dinonaktifkan — akun baru hanya lewat Google login.
    // /register tetap ada sebagai redirect agar link lama (mis. CTA landing) tidak 404.
    Route::get('/register', fn () => redirect()->route('login'))->name('register');
    // Login Google (hanya user site — admin tetap pakai email/password).
    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->middleware('throttle:5,1')->name('google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::view('/privacy-policy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::post('/payments/tripay/callback', [PaymentController::class, 'callback'])->name('tripay.callback');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/keys', [DashboardController::class, 'keys'])->name('keys');
    Route::get('/usage', [DashboardController::class, 'usage'])->name('usage');
    Route::get('/usage/export', [DashboardController::class, 'export'])->name('usage.export');
    Route::get('/billing', [DashboardController::class, 'billing'])->name('billing');
    Route::get('/redeem-code', [RedeemCodeController::class, 'create'])->name('redeem-codes.create');
    Route::post('/redeem-code', [RedeemCodeController::class, 'store'])->middleware('throttle:10,1')->name('redeem-codes.store');
    Route::get('/topup', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/topup', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{paymentOrder}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/api-health', [DashboardController::class, 'apiHealth'])->name('api-health');
    Route::get('/models', [DashboardController::class, 'models'])->name('models');
    Route::get('/plans', [DashboardController::class, 'plans'])->name('plans');
    Route::post('/plans/{plan}/purchase', [DashboardController::class, 'purchasePlan'])->middleware('throttle:5,1')->name('plans.purchase');
    Route::patch('/settings/payg', [DashboardController::class, 'updatePayg'])->name('settings.payg');
    Route::get('/status', [DashboardController::class, 'status'])->name('status');
    Route::get('/leaderboard', [DashboardController::class, 'leaderboard'])->name('leaderboard');
    Route::get('/docs', [DashboardController::class, 'docs'])->name('docs');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('support.store');
    Route::get('/support/{supportTicket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{supportTicket}/reply', [SupportController::class, 'reply'])->middleware('throttle:20,1')->name('support.reply');
    Route::get('/support/{supportTicket}/attachments/{supportAttachment}', [SupportController::class, 'attachment'])->name('support.attachments.show');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::patch('/inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.read-all');
    Route::delete('/inbox/delete-all', [InboxController::class, 'destroyAll'])->name('inbox.destroy-all');
    Route::patch('/inbox/{inboxMessage}/read', [InboxController::class, 'read'])->name('inbox.read');
    Route::delete('/inbox/{inboxMessage}', [InboxController::class, 'destroy'])->name('inbox.destroy');
    Route::get('/referral', [DashboardController::class, 'referral'])->name('referral');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::patch('/settings/profile', [DashboardController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::patch('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggle'])->name('api-keys.toggle');
    Route::patch('/api-keys/{apiKey}/no-expiry', [ApiKeyController::class, 'removeExpiry'])->name('api-keys.no-expiry');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
});
