<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use support\Request;
use app\service\AdminService;

class AdminController extends BaseController
{
    use DefinesAdminMenu;
    use RequiresGoogleAuth;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '权限管理',
                'slug' => 'auth',
                'path' => '/auth',
                'icon' => 'lock',
                'sort' => 90,
            ],
            'menu' => [
                'name' => '管理员',
                'slug' => 'admin-menu',
                'path' => '/auth/admin',
                'icon' => 'user',
                'component' => 'views/system/admin/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '管理员列表', 'slug' => 'admin:list', 'path' => '/admin/admins', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建管理员', 'slug' => 'admin:create', 'path' => '/admin/admins', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新管理员', 'slug' => 'admin:update', 'path' => '/admin/admins/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除管理员', 'slug' => 'admin:delete', 'path' => '/admin/admins/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '重置管理员密码', 'slug' => 'admin:reset-password', 'path' => '/admin/admins/*/password', 'method' => 'PUT', 'sort' => 5],
            ],
        ];
    }

    protected AdminService $adminService;

    public function __construct(?AdminService $adminService = null)
    {
        $this->adminService = $adminService ?? new AdminService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'status']);
        return success($this->adminService->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $this->requireGoogleAuth($request);
        $data = $request->only(['username', 'password', 'nickname', 'status', 'role_ids']);
        if (empty($data['username']) || empty($data['password'])) {
            return fail(42201, '用户名和密码不能为空');
        }
        $data['status'] = (int) ($data['status'] ?? 1);
        return success($this->adminService->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        if ($request->post('password') !== null || $request->post('new_password') !== null) {
            return fail(42201, '编辑管理员资料时请使用「重置密码」修改密码');
        }

        $data = $request->only(['nickname', 'status', 'role_ids', 'avatar']);
        return success($this->adminService->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $this->adminService->delete($id);
        return success($id, '删除成功');
    }

    public function updatePassword(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $operatorId = $this->adminId($request);
        $oldPassword = (string) $request->post('old_password', '');
        $newPassword = (string) $request->post('new_password', '');

        if ($newPassword === '') {
            return fail(42201, '新密码不能为空');
        }
        if (strlen($newPassword) < 6) {
            return fail(42201, '新密码至少 6 位');
        }

        // 改自己的密码必须提供原密码；列表重置他人密码不需要
        if ($operatorId === $id && $oldPassword === '') {
            return fail(42201, '原密码不能为空');
        }

        $this->adminService->updatePassword($id, $oldPassword, $newPassword, $operatorId);
        return success(null, '密码修改成功');
    }
}
