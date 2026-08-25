<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\pay\WalletService;
use support\Request;

class PayWalletController extends BaseController
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
                'name' => '钱包管理',
                'slug' => 'pay-wallet-menu',
                'path' => '/pay/wallet',
                'icon' => 'wallet',
                'component' => 'views/pay/wallet/index',
                'sort' => 6,
            ],
            'apis' => [
                ['name' => '热钱包余额', 'slug' => 'pay:wallet:hot-balance', 'path' => '/admin/pay/wallets/hot-balance', 'method' => 'GET', 'sort' => 1],
                ['name' => '地址池列表', 'slug' => 'pay:wallet:list', 'path' => '/admin/pay/wallets', 'method' => 'GET', 'sort' => 2],
            ],
        ];
    }

    protected WalletService $walletService;

    public function __construct(?WalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->walletService->list($page, $limit, $request->get()));
    }

    public function hotBalance(Request $request)
    {
        return success($this->walletService->hotWalletStatus());
    }
}
