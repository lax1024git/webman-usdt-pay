<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\DepositOrderService;
use support\Request;

class PayDepositController extends BaseController
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
                'name' => '入金订单',
                'slug' => 'pay-deposit-menu',
                'path' => '/pay/deposit',
                'icon' => 'download',
                'component' => 'views/pay/deposit/index',
                'sort' => 3,
            ],
            'apis' => [
                ['name' => '入金列表', 'slug' => 'pay:deposit:list', 'path' => '/admin/pay/deposits', 'method' => 'GET', 'sort' => 1],
                ['name' => '入金详情', 'slug' => 'pay:deposit:show', 'path' => '/admin/pay/deposits/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '人工补单', 'slug' => 'pay:deposit:manual', 'path' => '/admin/pay/deposits/*/manual-credit', 'method' => 'POST', 'sort' => 3],
            ],
        ];
    }

    protected DepositOrderService $depositOrderService;

    public function __construct(?DepositOrderService $depositOrderService = null)
    {
        $this->depositOrderService = $depositOrderService ?? new DepositOrderService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->depositOrderService->list($page, $limit, $request->get()));
    }

    public function show(Request $request, int $id)
    {
        return success($this->depositOrderService->show($id));
    }

    public function manualCredit(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $paidAmount = (string) $request->post('paid_amount', '');
        $txHash = $request->post('tx_hash');
        if ($paidAmount === '') {
            return fail(42201, 'paid_amount 不能为空');
        }
        return success(
            $this->depositOrderService->manualCredit($id, $paidAmount, is_string($txHash) ? $txHash : null),
            '补单成功'
        );
    }
}
