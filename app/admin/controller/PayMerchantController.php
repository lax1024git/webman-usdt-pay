<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\admin\controller\concerns\RequiresGoogleAuth;
use app\service\pay\MerchantService;
use support\Request;

class PayMerchantController extends BaseController
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
                'name' => '商户管理',
                'slug' => 'pay-merchant-menu',
                'path' => '/pay/merchant',
                'icon' => 'user',
                'component' => 'views/pay/merchant/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '商户列表', 'slug' => 'pay:merchant:list', 'path' => '/admin/pay/merchants', 'method' => 'GET', 'sort' => 1],
                ['name' => '商户详情', 'slug' => 'pay:merchant:show', 'path' => '/admin/pay/merchants/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建商户', 'slug' => 'pay:merchant:store', 'path' => '/admin/pay/merchants', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新商户', 'slug' => 'pay:merchant:update', 'path' => '/admin/pay/merchants/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '重置商户密钥', 'slug' => 'pay:merchant:reset-secret', 'path' => '/admin/pay/merchants/*/reset-secret', 'method' => 'POST', 'sort' => 5],
            ],
        ];
    }

    protected MerchantService $merchantService;

    public function __construct(?MerchantService $merchantService = null)
    {
        $this->merchantService = $merchantService ?? new MerchantService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->merchantService->list($page, $limit, $request->get()));
    }

    public function show(Request $request, int $id)
    {
        return success($this->merchantService->show($id));
    }

    public function store(Request $request)
    {
        $this->requireGoogleAuth($request);
        $result = $this->merchantService->create($request->post());
        return success([
            'merchant' => $result['merchant'],
            'api_key' => $result['merchant']->api_key,
            'api_secret' => $result['api_secret'],
        ], '创建成功，请妥善保存 API Secret');
    }

    public function update(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        return success($this->merchantService->update($id, $request->post()), '更新成功');
    }

    public function resetSecret(Request $request, int $id)
    {
        $this->requireGoogleAuth($request);
        return success($this->merchantService->resetSecret($id), '密钥已重置');
    }
}
