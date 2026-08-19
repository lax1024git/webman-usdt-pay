<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\AdminIpWhitelistService;
use support\Request;

class IpWhitelistController extends BaseController
{
    use DefinesAdminMenu;
    use RequiresGoogleAuth;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '系统管理',
                'slug' => 'system-config',
                'path' => '/system-config',
                'icon' => 'setting',
                'sort' => 100,
            ],
            'menu' => [
                'name' => 'IP白名单',
                'slug' => 'ip-whitelist-menu',
                'path' => '/system-config/ip-whitelist',
                'icon' => 'lock',
                'component' => 'views/system/ipWhitelist/index',
                'sort' => 6,
            ],
            'apis' => [
                ['name' => '白名单列表', 'slug' => 'ipWhitelist:list', 'path' => '/admin/ip-whitelists', 'method' => 'GET', 'sort' => 1],
                ['name' => '新增白名单', 'slug' => 'ipWhitelist:create', 'path' => '/admin/ip-whitelists', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新白名单', 'slug' => 'ipWhitelist:update', 'path' => '/admin/ip-whitelists/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除白名单', 'slug' => 'ipWhitelist:delete', 'path' => '/admin/ip-whitelists/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    protected AdminIpWhitelistService $service;

    public function __construct(?AdminIpWhitelistService $service = null)
    {
        $this->service = $service ?? new AdminIpWhitelistService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'enabled']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $this->requireGoogleAuth($request);
        $data = $request->only(['ip_rule', 'remark', 'enabled']);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $data = $request->only(['ip_rule', 'remark', 'enabled']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $this->service->delete($id);
        return success($id, '删除成功');
    }
}

