<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\model\pay\DepositOrderModel;
use support\Request;

class DepositController extends BaseController
{
    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $query = DepositOrderModel::query()
            ->where('merchant_id', $this->merchantId($request))
            ->orderByDesc('id');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($orderNo = $request->get('order_no')) {
            $query->where('order_no', $orderNo);
        }
        if ($outTradeNo = $request->get('out_trade_no')) {
            $query->where('out_trade_no', $outTradeNo);
        }

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return success(['total' => $total, 'items' => $items]);
    }

    public function show(Request $request, int $id)
    {
        $order = DepositOrderModel::where('merchant_id', $this->merchantId($request))
            ->where('id', $id)
            ->first();
        if (!$order) {
            return fail(43008, '订单不存在');
        }
        return success($order);
    }
}
