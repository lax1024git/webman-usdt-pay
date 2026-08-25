<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\pay\PlatformService;
use support\Request;

class PayReportController extends BaseController
{
    use DefinesAdminMenu;

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
                'name' => '运营报表',
                'slug' => 'pay-report-menu',
                'path' => '/pay/report',
                'icon' => 'histogram',
                'component' => 'views/pay/report/index',
                'sort' => 8,
            ],
            'apis' => [
                ['name' => '报表汇总', 'slug' => 'pay:report:summary', 'path' => '/admin/pay/reports/summary', 'method' => 'GET', 'sort' => 1],
                ['name' => '日报表', 'slug' => 'pay:report:daily', 'path' => '/admin/pay/reports/daily', 'method' => 'GET', 'sort' => 2],
                ['name' => '商户报表', 'slug' => 'pay:report:merchant', 'path' => '/admin/pay/reports/merchant/*', 'method' => 'GET', 'sort' => 3],
            ],
        ];
    }

    public function __construct(protected ?PlatformService $platformService = null)
    {
        $this->platformService = $platformService ?? new PlatformService();
    }

    public function summary(Request $request)
    {
        return success($this->platformService->reportSummary($request->get()));
    }

    public function daily(Request $request)
    {
        return success($this->platformService->reportDaily($request->get()));
    }

    public function merchant(Request $request, int $id)
    {
        return success($this->platformService->reportMerchant($id, $request->get()));
    }
}
