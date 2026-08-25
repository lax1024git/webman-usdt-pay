<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\CollectionService;
use support\Request;

class PayCollectionController extends BaseController
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
                'name' => '归集任务',
                'slug' => 'pay-collection-menu',
                'path' => '/pay/collection',
                'icon' => 'refresh',
                'component' => 'views/pay/collection/index',
                'sort' => 7,
            ],
            'apis' => [
                ['name' => '归集列表', 'slug' => 'pay:collection:list', 'path' => '/admin/pay/collections', 'method' => 'GET', 'sort' => 1],
                ['name' => '触发归集', 'slug' => 'pay:collection:trigger', 'path' => '/admin/pay/collections/trigger', 'method' => 'POST', 'sort' => 2],
                ['name' => '重试归集', 'slug' => 'pay:collection:retry', 'path' => '/admin/pay/collections/*/retry', 'method' => 'POST', 'sort' => 3],
            ],
        ];
    }

    public function __construct(protected ?CollectionService $service = null)
    {
        $this->service = $service ?? new CollectionService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->get()));
    }

    public function trigger(Request $request)
    {
        $this->requireGoogleAuth($request);
        if (!filter_var(env('PAY_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return fail(42201, '未开启 PAY_COLLECTION_ENABLED');
        }
        $count = $this->service->trigger((int) $request->post('platform_id', 0));
        return success(['queued' => $count], "已入队 {$count} 笔归集任务");
    }

    public function retry(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        $this->service->retry($id);
        return success([], '已重新入队');
    }
}
