<?php

declare(strict_types=1);

namespace app\admin\controller\concerns;

use app\exception\BusinessException;
use app\service\PermissionService;
use app\support\ErrorCode;
use support\Request;

trait RequiresPermission
{
    protected function requireButtonPermission(Request $request, string $slug): void
    {
        $this->requireAnyButtonPermission($request, [$slug]);
    }

    /**
     * @param list<string> $slugs
     */
    protected function requireAnyButtonPermission(Request $request, array $slugs): void
    {
        $adminId = (int) ($request->admin_id ?? 0);
        if ($adminId <= 0) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $permissionService = new PermissionService();
        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '' && $permissionService->checkButtonPermission($adminId, $slug)) {
                return;
            }
        }

        throw new BusinessException(ErrorCode::FORBIDDEN, '无按钮操作权限');
    }
}
