<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\MerchantAccountModel;
use app\model\pay\MerchantLedgerModel;
use app\support\Decimal;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class LedgerService
{
    public function getOrCreateAccount(int $merchantId, string $currency, string $chain): MerchantAccountModel
    {
        $account = MerchantAccountModel::query()
            ->where('merchant_id', $merchantId)
            ->where('currency', $currency)
            ->where('chain', $chain)
            ->first();

        if ($account) {
            return $account;
        }

        return MerchantAccountModel::create([
            'merchant_id' => $merchantId,
            'currency' => $currency,
            'chain' => $chain,
            'available' => '0.000000',
            'frozen' => '0.000000',
            'version' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getBalance(int $merchantId, string $currency, string $chain): array
    {
        $account = $this->getOrCreateAccount($merchantId, $currency, $chain);

        return [
            'currency' => $currency,
            'chain' => $chain,
            'available' => Decimal::format((string) $account->available),
            'frozen' => Decimal::format((string) $account->frozen),
        ];
    }

    public function listLedgers(int $merchantId, int $page, int $limit, array $filters = []): array
    {
        $query = MerchantLedgerModel::query()->where('merchant_id', $merchantId);
        if (!empty($filters['biz_type'])) {
            $query->where('biz_type', $filters['biz_type']);
        }
        if (!empty($filters['order_no'])) {
            $query->where('order_no', $filters['order_no']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(static function (MerchantLedgerModel $row) {
                $data = $row->toArray();
                $data['change_amount'] = Decimal::format((string) $row->change_amount);
                $data['available_after'] = Decimal::format((string) $row->available_after);
                $data['frozen_after'] = Decimal::format((string) $row->frozen_after);
                return $data;
            });

        return ['total' => $total, 'items' => $items];
    }

    public function creditDeposit(
        int $merchantId,
        string $currency,
        string $chain,
        string $amount,
        int $bizId,
        string $orderNo,
        string $remark = ''
    ): void {
        DB::connection()->transaction(function () use ($merchantId, $currency, $chain, $amount, $bizId, $orderNo, $remark) {
            $account = $this->lockAccount($merchantId, $currency, $chain);
            $available = Decimal::add((string) $account->available, $amount);
            $account->update([
                'available' => $available,
                'version' => $account->version + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeLedger($account, MerchantLedgerModel::BIZ_DEPOSIT, $bizId, $orderNo, $amount, $available, (string) $account->frozen, $remark);
        });
    }

    public function freezeWithdraw(
        int $merchantId,
        string $currency,
        string $chain,
        string $amount,
        int $bizId,
        string $orderNo
    ): void {
        DB::connection()->transaction(function () use ($merchantId, $currency, $chain, $amount, $bizId, $orderNo) {
            $account = $this->lockAccount($merchantId, $currency, $chain);
            if (Decimal::cmp((string) $account->available, $amount) < 0) {
                throw new BusinessException(ErrorCode::PAY_INSUFFICIENT_BALANCE);
            }
            $available = Decimal::sub((string) $account->available, $amount);
            $frozen = Decimal::add((string) $account->frozen, $amount);
            $account->update([
                'available' => $available,
                'frozen' => $frozen,
                'version' => $account->version + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeLedger($account, MerchantLedgerModel::BIZ_WITHDRAW_FREEZE, $bizId, $orderNo, Decimal::format('-' . ltrim($amount, '-')), $available, $frozen, '出金冻结');
        });
    }

    public function unfreezeWithdraw(
        int $merchantId,
        string $currency,
        string $chain,
        string $amount,
        int $bizId,
        string $orderNo,
        string $remark = '出金解冻'
    ): void {
        DB::connection()->transaction(function () use ($merchantId, $currency, $chain, $amount, $bizId, $orderNo, $remark) {
            $account = $this->lockAccount($merchantId, $currency, $chain);
            $available = Decimal::add((string) $account->available, $amount);
            $frozen = Decimal::sub((string) $account->frozen, $amount);
            if (Decimal::cmp($frozen, '0') < 0) {
                $frozen = '0.000000';
            }
            $account->update([
                'available' => $available,
                'frozen' => $frozen,
                'version' => $account->version + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeLedger($account, MerchantLedgerModel::BIZ_WITHDRAW_UNFREEZE, $bizId, $orderNo, $amount, $available, $frozen, $remark);
        });
    }

    public function confirmWithdraw(
        int $merchantId,
        string $currency,
        string $chain,
        string $frozenAmount,
        int $bizId,
        string $orderNo
    ): void {
        DB::connection()->transaction(function () use ($merchantId, $currency, $chain, $frozenAmount, $bizId, $orderNo) {
            $account = $this->lockAccount($merchantId, $currency, $chain);
            $frozen = Decimal::sub((string) $account->frozen, $frozenAmount);
            if (Decimal::cmp($frozen, '0') < 0) {
                $frozen = '0.000000';
            }
            $account->update([
                'frozen' => $frozen,
                'version' => $account->version + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeLedger(
                $account,
                MerchantLedgerModel::BIZ_WITHDRAW_SUCCESS,
                $bizId,
                $orderNo,
                Decimal::format('-' . ltrim($frozenAmount, '-')),
                (string) $account->available,
                $frozen,
                '出金完成'
            );
        });
    }

    private function lockAccount(int $merchantId, string $currency, string $chain): MerchantAccountModel
    {
        $account = MerchantAccountModel::query()
            ->where('merchant_id', $merchantId)
            ->where('currency', $currency)
            ->where('chain', $chain)
            ->lockForUpdate()
            ->first();

        if (!$account) {
            $this->getOrCreateAccount($merchantId, $currency, $chain);
            $account = MerchantAccountModel::query()
                ->where('merchant_id', $merchantId)
                ->where('currency', $currency)
                ->where('chain', $chain)
                ->lockForUpdate()
                ->first();
        }

        if (!$account) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '账户创建失败');
        }

        return $account;
    }

    private function writeLedger(
        MerchantAccountModel $account,
        string $bizType,
        int $bizId,
        string $orderNo,
        string $changeAmount,
        string $availableAfter,
        string $frozenAfter,
        string $remark
    ): void {
        MerchantLedgerModel::create([
            'merchant_id' => $account->merchant_id,
            'account_id' => $account->id,
            'biz_type' => $bizType,
            'biz_id' => $bizId,
            'order_no' => $orderNo,
            'change_amount' => Decimal::format($changeAmount),
            'available_after' => Decimal::format($availableAfter),
            'frozen_after' => Decimal::format($frozenAfter),
            'remark' => $remark,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
