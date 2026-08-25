<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\PlatformService;
use support\Request;

class PayBlacklistController extends BaseController
{
    use DefinesAdminMenu;
    use RequiresGoogleAuth;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '充付管理',
                'slug' => 'pay',
                'path' => '/pay',
                'icon' => 'money',
                'sort' => 50,
            ],
            'menu' => [
                'name' => '地址黑名单',
                'slug' => 'pay-blacklist-menu',
                'path' => '/pay/blacklist',
                'icon' => 'warning',
                'component' => 'views/pay/blacklist/index',
                'sort' => 7,
            ],
            'apis' => [
                ['name' => '黑名单列表', 'slug' => 'pay:blacklist:list', 'path' => '/admin/pay/blacklists', 'method' => 'GET', 'sort' => 1],
                ['name' => '添加黑名单', 'slug' => 'pay:blacklist:create', 'path' => '/admin/pay/blacklists', 'method' => 'POST', 'sort' => 2],
                ['name' => '删除黑名单', 'slug' => 'pay:blacklist:delete', 'path' => '/admin/pay/blacklists/*', 'method' => 'DELETE', 'sort' => 3],
            ],
        ];
    }

    public function __construct(protected ?PlatformService $platformService = null)
    {
        $this->platformService = $platformService ?? new PlatformService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->platformService->blacklistList($page, $limit, $request->get()));
    }

    public function store(Request $request)
    {
        $this->requireGoogleAuth($request);
        return success($this->platformService->addBlacklist($request->post()), '添加成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $this->platformService->deleteBlacklist($id);
        return success([], '删除成功');
    }
}
