<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;

/**
 * 控制台首页（纯欢迎页，无业务统计）
 */
class DashboardController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '控制台',
                'slug' => 'console',
                'path' => '/console',
                'icon' => 'dashboard',
                'sort' => 1,
            ],
            'menu' => [
                'name' => '首页',
                'slug' => 'dashboard-menu',
                'path' => '/console/dashboard',
                'icon' => 'dashboard',
                'component' => 'views/dashboard/index',
                'sort' => 1,
            ],
            'apis' => [],
        ];
    }
}
