<?php

declare(strict_types=1);

use Webman\Route;
use app\api\controller\PayDepositController;
use app\api\controller\PayWithdrawController;
use app\api\controller\PayAccountController;
use app\middleware\MerchantAuthMiddleware;

Route::options('/api/pay/{path:.+}', fn () => response('', 204));

Route::group('/api/pay', function () {
    Route::post('/deposits', [PayDepositController::class, 'store']);
    Route::get('/deposits/query', [PayDepositController::class, 'query']);
    Route::get('/deposits/{order_no}', [PayDepositController::class, 'show']);

    Route::post('/withdrawals', [PayWithdrawController::class, 'store']);
    Route::get('/withdrawals/query', [PayWithdrawController::class, 'query']);
    Route::get('/withdrawals/{order_no}', [PayWithdrawController::class, 'show']);

    Route::get('/account/balance', [PayAccountController::class, 'balance']);
})->middleware([MerchantAuthMiddleware::class]);
