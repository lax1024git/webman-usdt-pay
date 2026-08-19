<?php

declare(strict_types=1);

namespace app\admin\controller\concerns;

use app\exception\BusinessException;
use app\model\sys\AdminModel;
use app\service\AdminGoogleAuthService;
use app\support\ErrorCode;
use support\Request;

trait RequiresGoogleAuth
{
    protected function requireGoogleAuth(Request $request): void
    {
        $service = new AdminGoogleAuthService();
        if (!$service->isOperationVerifyEnabled()) {
            return;
        }

        $adminId = (int) ($request->admin_id ?? 0);
        if ($adminId <= 0) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $admin = AdminModel::find($adminId);
        if (!$admin) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $code = $request->input('google_code', $request->post('google_code', $request->get('google_code', '')));
        $code = is_string($code) ? $code : (string) $code;

        if (!$service->isBound($admin)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请先在个人中心绑定谷歌验证器');
        }

        $service->assertCode($admin, $code);
    }
}
