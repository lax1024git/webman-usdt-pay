<?php

declare(strict_types=1);

namespace app\api\controller;

use app\model\pay\MerchantModel;
use app\service\pay\DepositOrderService;
use support\Request;

class PayDepositController
{
    protected DepositOrderService $depositOrderService;

    public function __construct(?DepositOrderService $depositOrderService = null)
    {
        $this->depositOrderService = $depositOrderService ?? new DepositOrderService();
    }

    public function store(Request $request)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        return success($this->depositOrderService->create($merchant, $request->post()));
    }

    public function show(Request $request, string $orderNo)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        $outTradeNo = $request->get('out_trade_no');
        return success($this->depositOrderService->findForMerchant(
            $merchant,
            $orderNo !== 'query' ? $orderNo : null,
            is_string($outTradeNo) ? $outTradeNo : null
        ));
    }

    public function query(Request $request)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        return success($this->depositOrderService->findForMerchant(
            $merchant,
            $request->get('order_no') ? (string) $request->get('order_no') : null,
            $request->get('out_trade_no') ? (string) $request->get('out_trade_no') : null
        ));
    }
}
