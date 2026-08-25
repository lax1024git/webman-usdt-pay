<?php

declare(strict_types=1);

use Webman\Route;
use app\merchant\controller\AuthController;
use app\merchant\controller\SettingsController;
use app\merchant\controller\DepositController;
use app\merchant\controller\WithdrawController;
use app\merchant\controller\AccountController;
use app\merchant\controller\WebhookLogController;
use app\merchant\controller\StatisticsController;
use app\merchant\controller\LedgerController;
use app\middleware\MerchantPortalAuthMiddleware;

Route::options('/merchant/{path:.+}', fn () => response('', 204));

// 无需认证
Route::post('/merchant/login', [AuthController::class, 'login']);
Route::post('/merchant/refresh', [AuthController::class, 'refresh']);

// 需认证
Route::group('/merchant', function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/reset-secret', [SettingsController::class, 'resetSecret']);

    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/deposits/{id:\d+}', [DepositController::class, 'show']);

    Route::get('/withdrawals', [WithdrawController::class, 'index']);
    Route::get('/withdrawals/{id:\d+}', [WithdrawController::class, 'show']);

    Route::get('/account/balance', [AccountController::class, 'balance']);
    Route::get('/account/ledgers', [LedgerController::class, 'index']);

    Route::get('/webhook-logs', [WebhookLogController::class, 'index']);
    Route::post('/webhook-logs/{id:\d+}/retry', [WebhookLogController::class, 'retry']);

    Route::get('/statistics', [StatisticsController::class, 'index']);
})->middleware([MerchantPortalAuthMiddleware::class]);
