<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\pay\WebhookService;
use support\Request;

class PayWebhookLogController extends BaseController
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
                'name' => '回调日志',
                'slug' => 'pay-webhook-menu',
                'path' => '/pay/webhook-log',
                'icon' => 'message',
                'component' => 'views/pay/webhook-log/index',
                'sort' => 5,
            ],
            'apis' => [
                ['name' => '回调日志列表', 'slug' => 'pay:webhook:list', 'path' => '/admin/pay/webhook-logs', 'method' => 'GET', 'sort' => 1],
                ['name' => '重发回调', 'slug' => 'pay:webhook:retry', 'path' => '/admin/pay/webhook-logs/*/retry', 'method' => 'POST', 'sort' => 2],
            ],
        ];
    }

    protected WebhookService $webhookService;

    public function __construct(?WebhookService $webhookService = null)
    {
        $this->webhookService = $webhookService ?? new WebhookService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->webhookService->list($page, $limit, $request->get()));
    }

    public function retry(Request $request, int $id)
    {
        $this->webhookService->retry($id);
        return success([], '已加入重试队列');
    }
}
