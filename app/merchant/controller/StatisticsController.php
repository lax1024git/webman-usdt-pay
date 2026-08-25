<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\model\pay\DepositOrderModel;
use app\model\pay\WithdrawOrderModel;
use app\support\Decimal;
use support\Request;

class StatisticsController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $this->merchantId($request);
        $from = (string) $request->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $to = (string) $request->get('date_to', date('Y-m-d'));
        if (strlen($from) <= 10) {
            $from .= ' 00:00:00';
        }
        if (strlen($to) <= 10) {
            $to .= ' 23:59:59';
        }

        $depositQuery = DepositOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to]);

        $withdrawQuery = WithdrawOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to]);

        $depositCount = (int) $depositQuery->count();
        $withdrawCount = (int) $withdrawQuery->count();
        $depositAmount = Decimal::format((string) ($depositQuery->sum('paid_amount') ?? '0'));
        $depositNet = Decimal::format((string) ($depositQuery->sum('net_amount') ?? '0'));
        $depositFee = Decimal::format((string) ($depositQuery->sum('fee_amount') ?? '0'));
        $withdrawAmount = Decimal::format((string) ($withdrawQuery->sum('withdraw_amount') ?? '0'));
        $withdrawFee = Decimal::format((string) ($withdrawQuery->sum('fee_amount') ?? '0'));

        return success([
            'date_from' => $from,
            'date_to' => $to,
            'deposit_count' => $depositCount,
            'deposit_amount' => $depositAmount,
            'deposit_net' => $depositNet,
            'deposit_fee' => $depositFee,
            'withdraw_count' => $withdrawCount,
            'withdraw_amount' => $withdrawAmount,
            'withdraw_fee' => $withdrawFee,
            'net_inflow' => Decimal::sub($depositNet, $withdrawAmount),
        ]);
    }
}
