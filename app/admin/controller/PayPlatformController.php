<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\PlatformService;
use support\Request;

class PayPlatformController extends BaseController
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
                'name' => '支付通道',
                'slug' => 'pay-platform-menu',
                'path' => '/pay/platform',
                'icon' => 'connection',
                'component' => 'views/pay/platform/index',
                'sort' => 2,
            ],
            'apis' => [
                ['name' => '通道列表', 'slug' => 'pay:platform:list', 'path' => '/admin/pay/platforms', 'method' => 'GET', 'sort' => 1],
                ['name' => '更新通道', 'slug' => 'pay:platform:update', 'path' => '/admin/pay/platforms/*', 'method' => 'PUT', 'sort' => 2],
            ],
        ];
    }

    protected PlatformService $platformService;

    public function __construct(?PlatformService $platformService = null)
    {
        $this->platformService = $platformService ?? new PlatformService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->platformService->list($page, $limit));
    }

    public function update(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        return success($this->platformService->update($id, $request->post()), '更新成功');
    }
}
