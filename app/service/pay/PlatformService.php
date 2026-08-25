<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\AddressBlacklistModel;
use app\model\pay\DepositOrderModel;
use app\model\pay\WithdrawOrderModel;
use app\support\Decimal;
use app\model\pay\PlatformModel;
use app\support\ErrorCode;

class PlatformService
{
    public function list(int $page, int $limit): array
    {
        $query = PlatformModel::query()->orderBy('sort')->orderBy('id');
        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function getByCode(string $code): PlatformModel
    {
        $platform = PlatformModel::where('code', $code)->first();
        if (!$platform || (int) $platform->status !== 1) {
            throw new BusinessException(ErrorCode::PAY_PLATFORM_UNAVAILABLE);
        }

        return $platform;
    }

    public function update(int $id, array $data): PlatformModel
    {
        $platform = PlatformModel::find($id);
        if (!$platform) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '通道不存在');
        }

        $allowed = [
            'name', 'min_deposit_amount', 'max_deposit_amount', 'min_withdraw_amount', 'max_withdraw_amount',
            'deposit_confirmations', 'withdraw_confirmations', 'deposit_expire_seconds',
            'amount_match_mode', 'status', 'config', 'sort',
        ];
        $platform->update(array_intersect_key($data, array_flip($allowed)));

        return $platform->fresh();
    }

    public function blacklistList(int $page, int $limit, array $filters = []): array
    {
        $query = AddressBlacklistModel::query()->orderByDesc('id');
        if (!empty($filters['chain'])) {
            $query->where('chain', $filters['chain']);
        }
        if (!empty($filters['address'])) {
            $query->where('address', 'like', '%' . $filters['address'] . '%');
        }

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function addBlacklist(array $data): AddressBlacklistModel
    {
        $chain = trim((string) ($data['chain'] ?? 'TRC20'));
        $address = trim((string) ($data['address'] ?? ''));
        if ($address === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '地址不能为空');
        }

        $exists = AddressBlacklistModel::query()
            ->where('chain', $chain)
            ->where('address', $address)
            ->first();
        if ($exists) {
            return $exists;
        }

        return AddressBlacklistModel::create([
            'chain' => $chain,
            'address' => $address,
            'reason' => (string) ($data['reason'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteBlacklist(int $id): void
    {
        $row = AddressBlacklistModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '黑名单记录不存在');
        }

        $row->delete();
    }

    public function reportSummary(array $filters = []): array
    {
        $from = (string) ($filters['date_from'] ?? date('Y-m-d 00:00:00'));
        $to = (string) ($filters['date_to'] ?? date('Y-m-d 23:59:59'));
        if (strlen($from) <= 10) {
            $from .= ' 00:00:00';
        }
        if (strlen($to) <= 10) {
            $to .= ' 23:59:59';
        }

        $depositQuery = DepositOrderModel::query()
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to]);
        $withdrawQuery = WithdrawOrderModel::query()
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to]);

        $depositCount = (int) $depositQuery->count();
        $withdrawCount = (int) $withdrawQuery->count();
        $depositAmount = Decimal::format((string) ($depositQuery->sum('paid_amount') ?? '0'));
        $depositNet = Decimal::format((string) ($depositQuery->sum('net_amount') ?? '0'));
        $depositFee = Decimal::format((string) ($depositQuery->sum('fee_amount') ?? '0'));
        $withdrawAmount = Decimal::format((string) ($withdrawQuery->sum('withdraw_amount') ?? '0'));
        $withdrawPayout = Decimal::format((string) ($withdrawQuery->sum('payout_amount') ?? '0'));
        $withdrawFee = Decimal::format((string) ($withdrawQuery->sum('fee_amount') ?? '0'));
        $platformFeeIncome = Decimal::add($depositFee, $withdrawFee);

        $merchantRows = DepositOrderModel::query()
            ->selectRaw('merchant_id, COUNT(*) as deposit_count, COALESCE(SUM(net_amount),0) as deposit_net')
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->groupBy('merchant_id')
            ->get()
            ->keyBy('merchant_id');

        $withdrawRows = WithdrawOrderModel::query()
            ->selectRaw('merchant_id, COUNT(*) as withdraw_count, COALESCE(SUM(withdraw_amount),0) as withdraw_amount')
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->groupBy('merchant_id')
            ->get()
            ->keyBy('merchant_id');

        $merchantIds = array_values(array_unique(array_merge(
            array_map('intval', array_keys($merchantRows->all())),
            array_map('intval', array_keys($withdrawRows->all()))
        )));

        $merchantStats = [];
        foreach ($merchantIds as $merchantId) {
            $deposit = $merchantRows->get($merchantId);
            $withdraw = $withdrawRows->get($merchantId);
            $merchant = \app\model\pay\MerchantModel::find($merchantId);
            $depositNetValue = Decimal::format((string) ($deposit?->deposit_net ?? '0'));
            $withdrawAmountValue = Decimal::format((string) ($withdraw?->withdraw_amount ?? '0'));
            $merchantStats[] = [
                'merchant_id' => $merchantId,
                'merchant_name' => $merchant?->name ?? '',
                'merchant_no' => $merchant?->merchant_no ?? '',
                'deposit_count' => (int) ($deposit?->deposit_count ?? 0),
                'deposit_net' => $depositNetValue,
                'withdraw_count' => (int) ($withdraw?->withdraw_count ?? 0),
                'withdraw_amount' => $withdrawAmountValue,
                'net_inflow' => Decimal::sub($depositNetValue, $withdrawAmountValue),
            ];
        }

        usort($merchantStats, static fn (array $a, array $b): int => Decimal::cmp($b['deposit_net'], $a['deposit_net']));

        return [
            'date_from' => $from,
            'date_to' => $to,
            'summary' => [
                'deposit_count' => $depositCount,
                'deposit_amount' => $depositAmount,
                'deposit_net' => $depositNet,
                'deposit_fee' => $depositFee,
                'withdraw_count' => $withdrawCount,
                'withdraw_amount' => $withdrawAmount,
                'withdraw_payout' => $withdrawPayout,
                'withdraw_fee' => $withdrawFee,
                'platform_fee_income' => $platformFeeIncome,
                'net_inflow' => Decimal::sub($depositNet, $withdrawAmount),
            ],
            'merchant_stats' => $merchantStats,
        ];
    }

    public function reportDaily(array $filters = []): array
    {
        $from = (string) ($filters['date_from'] ?? date('Y-m-d', strtotime('-7 days')));
        $to = (string) ($filters['date_to'] ?? date('Y-m-d'));
        $fromDt = strlen($from) <= 10 ? $from . ' 00:00:00' : $from;
        $toDt = strlen($to) <= 10 ? $to . ' 23:59:59' : $to;

        $depositRows = DepositOrderModel::query()
            ->selectRaw('DATE(succeeded_at) as day, COUNT(*) as deposit_count, COALESCE(SUM(paid_amount),0) as deposit_amount, COALESCE(SUM(net_amount),0) as deposit_net')
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$fromDt, $toDt])
            ->groupByRaw('DATE(succeeded_at)')
            ->get()
            ->keyBy('day');

        $withdrawRows = WithdrawOrderModel::query()
            ->selectRaw('DATE(succeeded_at) as day, COUNT(*) as withdraw_count, COALESCE(SUM(withdraw_amount),0) as withdraw_amount')
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$fromDt, $toDt])
            ->groupByRaw('DATE(succeeded_at)')
            ->get()
            ->keyBy('day');

        $days = [];
        $cursor = strtotime(substr($fromDt, 0, 10));
        $end = strtotime(substr($toDt, 0, 10));
        while ($cursor <= $end) {
            $day = date('Y-m-d', $cursor);
            $deposit = $depositRows->get($day);
            $withdraw = $withdrawRows->get($day);
            $depositNet = Decimal::format((string) ($deposit?->deposit_net ?? '0'));
            $withdrawAmount = Decimal::format((string) ($withdraw?->withdraw_amount ?? '0'));
            $days[] = [
                'date' => $day,
                'deposit_count' => (int) ($deposit?->deposit_count ?? 0),
                'deposit_amount' => Decimal::format((string) ($deposit?->deposit_amount ?? '0')),
                'deposit_net' => $depositNet,
                'withdraw_count' => (int) ($withdraw?->withdraw_count ?? 0),
                'withdraw_amount' => $withdrawAmount,
                'net_inflow' => Decimal::sub($depositNet, $withdrawAmount),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        return [
            'date_from' => $fromDt,
            'date_to' => $toDt,
            'items' => $days,
        ];
    }

    public function reportMerchant(int $merchantId, array $filters = []): array
    {
        $merchant = \app\model\pay\MerchantModel::find($merchantId);
        if (!$merchant) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '商户不存在');
        }

        $from = (string) ($filters['date_from'] ?? date('Y-m-d 00:00:00'));
        $to = (string) ($filters['date_to'] ?? date('Y-m-d 23:59:59'));
        if (strlen($from) <= 10) {
            $from .= ' 00:00:00';
        }
        if (strlen($to) <= 10) {
            $to .= ' 23:59:59';
        }

        $depositCount = (int) DepositOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->count();
        $depositAmount = Decimal::format((string) (DepositOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->sum('paid_amount') ?? '0'));
        $depositNet = Decimal::format((string) (DepositOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', DepositOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->sum('net_amount') ?? '0'));
        $withdrawCount = (int) WithdrawOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->count();
        $withdrawAmount = Decimal::format((string) (WithdrawOrderModel::query()
            ->where('merchant_id', $merchantId)
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('succeeded_at', [$from, $to])
            ->sum('withdraw_amount') ?? '0'));

        $ledgers = \app\model\pay\MerchantLedgerModel::query()
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'merchant' => [
                'id' => $merchant->id,
                'merchant_no' => $merchant->merchant_no,
                'name' => $merchant->name,
            ],
            'date_from' => $from,
            'date_to' => $to,
            'summary' => [
                'deposit_count' => $depositCount,
                'deposit_amount' => $depositAmount,
                'deposit_net' => $depositNet,
                'withdraw_count' => $withdrawCount,
                'withdraw_amount' => $withdrawAmount,
                'net_inflow' => Decimal::sub($depositNet, $withdrawAmount),
            ],
            'recent_ledgers' => $ledgers,
        ];
    }
}
