<?php

declare(strict_types=1);

namespace app\api\controller;

use app\model\pay\MerchantModel;
use app\service\pay\WithdrawOrderService;
use support\Request;

class PayWithdrawController
{
    protected WithdrawOrderService $withdrawOrderService;

    public function __construct(?WithdrawOrderService $withdrawOrderService = null)
    {
        $this->withdrawOrderService = $withdrawOrderService ?? new WithdrawOrderService();
    }

    public function store(Request $request)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        return success($this->withdrawOrderService->create($merchant, $request->post()));
    }

    public function show(Request $request, string $orderNo)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        $outTradeNo = $request->get('out_trade_no');
        return success($this->withdrawOrderService->findForMerchant(
            $merchant,
            $orderNo !== 'query' ? $orderNo : null,
            is_string($outTradeNo) ? $outTradeNo : null
        ));
    }

    public function query(Request $request)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        return success($this->withdrawOrderService->findForMerchant(
            $merchant,
            $request->get('order_no') ? (string) $request->get('order_no') : null,
            $request->get('out_trade_no') ? (string) $request->get('out_trade_no') : null
        ));
    }
}
