<?php

declare(strict_types=1);

use Webman\Route;
use app\admin\controller\AuthController;
use app\admin\controller\AdminController;
use app\admin\controller\RoleController;
use app\admin\controller\PermissionController;
use app\admin\controller\SettingController;
use app\admin\controller\LogController;
use app\admin\controller\UploadController;
use app\admin\controller\IpWhitelistController;
use app\admin\controller\LangController;
use app\admin\controller\LangTextController;
use app\admin\controller\DictController;
use app\admin\controller\NotificationController;
use app\admin\controller\ExportController;
use app\admin\controller\PayMerchantController;
use app\admin\controller\PayPlatformController;
use app\admin\controller\PayDepositController;
use app\admin\controller\PayWithdrawController;
use app\admin\controller\PayWebhookLogController;
use app\admin\controller\PayWalletController;
use app\admin\controller\PayCollectionController;
use app\admin\controller\PayBlacklistController;
use app\admin\controller\PayReportController;
use app\middleware\AuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\IpWhitelistMiddleware;

// CORS 预检请求（OPTIONS 不走路由匹配时中间件不会执行，需显式注册）
Route::options('/admin/{path:.+}', fn () => response('', 204));

// 无需认证
Route::get('/admin/captcha', [AuthController::class, 'captcha'])->middleware([IpWhitelistMiddleware::class]);
Route::get('/admin/login-status', [AuthController::class, 'loginStatus'])->middleware([IpWhitelistMiddleware::class]);
Route::get('/admin/branding', [AuthController::class, 'branding'])->middleware([IpWhitelistMiddleware::class]);
Route::post('/admin/login', [AuthController::class, 'login'])->middleware([IpWhitelistMiddleware::class]);
Route::post('/admin/refresh', [AuthController::class, 'refresh'])->middleware([IpWhitelistMiddleware::class]);

// 需要认证
Route::group('/admin', function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/menus', [AuthController::class, 'menus']);
    Route::get('/server-time', [AuthController::class, 'serverTime']);
    Route::get('/me/google-auth/setup', [AuthController::class, 'googleAuthSetup']);
    Route::post('/me/google-auth/bind', [AuthController::class, 'googleAuthBind']);
    Route::post('/me/google-auth/verify', [AuthController::class, 'googleAuthVerify']);

    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{id:\d+}', [AdminController::class, 'update']);
    Route::delete('/admins/{id:\d+}', [AdminController::class, 'destroy']);
    Route::put('/admins/{id:\d+}/password', [AdminController::class, 'updatePassword']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id:\d+}', [RoleController::class, 'update']);
    Route::delete('/roles/{id:\d+}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id:\d+}/permissions', [RoleController::class, 'permissions']);
    Route::put('/roles/{id:\d+}/permissions', [RoleController::class, 'assignPermissions']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{id:\d+}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id:\d+}', [PermissionController::class, 'destroy']);

    Route::get('/system-config', [SettingController::class, 'configBundle']);
    Route::put('/system-config', [SettingController::class, 'saveConfigBundle']);

    Route::get('/logs', [LogController::class, 'index']);

    Route::post('/upload/presign', [UploadController::class, 'presign']);

    Route::get('/ip-whitelists', [IpWhitelistController::class, 'index']);
    Route::post('/ip-whitelists', [IpWhitelistController::class, 'store']);
    Route::put('/ip-whitelists/{id:\d+}', [IpWhitelistController::class, 'update']);
    Route::delete('/ip-whitelists/{id:\d+}', [IpWhitelistController::class, 'destroy']);

    Route::get('/dicts/code/{code}', [DictController::class, 'byCode']);

    Route::get('/langs/options', [LangController::class, 'options']);
    Route::get('/langs', [LangController::class, 'index']);
    Route::get('/langs/{id:\d+}', [LangController::class, 'show']);
    Route::post('/langs', [LangController::class, 'store']);
    Route::put('/langs/{id:\d+}', [LangController::class, 'update']);
    Route::delete('/langs/{id:\d+}', [LangController::class, 'destroy']);

    Route::post('/lang-texts/export', [LangTextController::class, 'export']);
    Route::post('/lang-texts/import', [LangTextController::class, 'import']);
    Route::post('/lang-texts/translate', [LangTextController::class, 'translatePreview']);
    Route::post('/lang-texts/{id:\d+}/translate', [LangTextController::class, 'translate']);
    Route::get('/lang-texts', [LangTextController::class, 'index']);
    Route::get('/lang-texts/{id:\d+}', [LangTextController::class, 'show']);
    Route::post('/lang-texts', [LangTextController::class, 'store']);
    Route::delete('/lang-texts/{id:\d+}', [LangTextController::class, 'destroy']);

    Route::get('/dicts', [DictController::class, 'index']);
    Route::post('/dicts', [DictController::class, 'store']);
    Route::put('/dicts/{id:\d+}', [DictController::class, 'update']);
    Route::delete('/dicts/{id:\d+}', [DictController::class, 'destroy']);
    Route::get('/dicts/{id:\d+}/items', [DictController::class, 'items']);
    Route::put('/dicts/{id:\d+}/items', [DictController::class, 'saveItems']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{id:\d+}/read', [NotificationController::class, 'markRead']);
    Route::get('/push/config', [NotificationController::class, 'pushConfig']);

    Route::get('/exports', [ExportController::class, 'index']);
    Route::post('/exports', [ExportController::class, 'store']);
    Route::get('/exports/{id:\d+}', [ExportController::class, 'show']);
    Route::delete('/exports/{id:\d+}', [ExportController::class, 'destroy']);

    Route::get('/pay/merchants', [PayMerchantController::class, 'index']);
    Route::get('/pay/merchants/{id:\d+}', [PayMerchantController::class, 'show']);
    Route::post('/pay/merchants', [PayMerchantController::class, 'store']);
    Route::put('/pay/merchants/{id:\d+}', [PayMerchantController::class, 'update']);
    Route::post('/pay/merchants/{id:\d+}/reset-secret', [PayMerchantController::class, 'resetSecret']);

    Route::get('/pay/platforms', [PayPlatformController::class, 'index']);
    Route::put('/pay/platforms/{id:\d+}', [PayPlatformController::class, 'update']);

    Route::get('/pay/deposits', [PayDepositController::class, 'index']);
    Route::get('/pay/deposits/{id:\d+}', [PayDepositController::class, 'show']);
    Route::post('/pay/deposits/{id:\d+}/manual-credit', [PayDepositController::class, 'manualCredit']);

    Route::get('/pay/withdrawals', [PayWithdrawController::class, 'index']);
    Route::get('/pay/withdrawals/{id:\d+}', [PayWithdrawController::class, 'show']);
    Route::post('/pay/withdrawals/{id:\d+}/approve', [PayWithdrawController::class, 'approve']);
    Route::post('/pay/withdrawals/{id:\d+}/reject', [PayWithdrawController::class, 'reject']);
    Route::post('/pay/withdrawals/{id:\d+}/retry-broadcast', [PayWithdrawController::class, 'retryBroadcast']);

    Route::get('/pay/webhook-logs', [PayWebhookLogController::class, 'index']);
    Route::post('/pay/webhook-logs/{id:\d+}/retry', [PayWebhookLogController::class, 'retry']);

    Route::get('/pay/wallets', [PayWalletController::class, 'index']);
    Route::get('/pay/wallets/hot-balance', [PayWalletController::class, 'hotBalance']);
    Route::get('/pay/collections', [PayCollectionController::class, 'index']);
    Route::post('/pay/collections/trigger', [PayCollectionController::class, 'trigger']);
    Route::post('/pay/collections/{id:\d+}/retry', [PayCollectionController::class, 'retry']);
    Route::get('/pay/blacklists', [PayBlacklistController::class, 'index']);
    Route::post('/pay/blacklists', [PayBlacklistController::class, 'store']);
    Route::delete('/pay/blacklists/{id:\d+}', [PayBlacklistController::class, 'destroy']);
    Route::get('/pay/reports/summary', [PayReportController::class, 'summary']);
    Route::get('/pay/reports/daily', [PayReportController::class, 'daily']);
    Route::get('/pay/reports/merchant/{id:\d+}', [PayReportController::class, 'merchant']);
})->middleware([
    IpWhitelistMiddleware::class,
    AuthMiddleware::class,
    AdminLogMiddleware::class,
]);
