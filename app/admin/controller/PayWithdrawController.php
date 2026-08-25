<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\WithdrawOrderService;
use support\Request;

class PayWithdrawController extends BaseController
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
                'name' => '出金订单',
                'slug' => 'pay-withdraw-menu',
                'path' => '/pay/withdraw',
                'icon' => 'upload',
                'component' => 'views/pay/withdraw/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '出金列表', 'slug' => 'pay:withdraw:list', 'path' => '/admin/pay/withdrawals', 'method' => 'GET', 'sort' => 1],
                ['name' => '出金详情', 'slug' => 'pay:withdraw:show', 'path' => '/admin/pay/withdrawals/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '审核通过', 'slug' => 'pay:withdraw:approve', 'path' => '/admin/pay/withdrawals/*/approve', 'method' => 'POST', 'sort' => 3],
                ['name' => '审核驳回', 'slug' => 'pay:withdraw:reject', 'path' => '/admin/pay/withdrawals/*/reject', 'method' => 'POST', 'sort' => 4],
                ['name' => '重试广播', 'slug' => 'pay:withdraw:retry', 'path' => '/admin/pay/withdrawals/*/retry-broadcast', 'method' => 'POST', 'sort' => 5],
            ],
        ];
    }

    protected WithdrawOrderService $withdrawOrderService;

    public function __construct(?WithdrawOrderService $withdrawOrderService = null)
    {
        $this->withdrawOrderService = $withdrawOrderService ?? new WithdrawOrderService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->withdrawOrderService->list($page, $limit, $request->get()));
    }

    public function show(Request $request, int $id)
    {
        return success($this->withdrawOrderService->show($id));
    }

    public function approve(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        return success($this->withdrawOrderService->approve($id, $this->adminId($request)), '审核通过');
    }

    public function reject(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $reason = (string) $request->post('reject_reason', '');
        if ($reason === '') {
            return fail(42201, '驳回原因不能为空');
        }
        return success($this->withdrawOrderService->reject($id, $this->adminId($request), $reason), '已驳回');
    }

    public function retryBroadcast(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        return success($this->withdrawOrderService->retryBroadcast($id), '已重新加入广播队列');
    }
}
