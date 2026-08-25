<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\ChainTransactionModel;
use app\model\pay\MerchantModel;
use app\model\pay\PlatformModel;
use app\model\pay\WithdrawOrderModel;
use app\queue\redis\WithdrawBroadcastConsumer;
use app\support\chain\ChainFactory;
use app\support\Decimal;
use app\support\ErrorCode;
use app\support\pay\HotWalletConfig;
use app\support\pay\OrderNoGenerator;
use Illuminate\Database\Capsule\Manager as DB;
use Webman\RedisQueue\Redis;

class WithdrawOrderService
{
    public function __construct(
        protected ?PlatformService $platformService = null,
        protected ?LedgerService $ledgerService = null,
        protected ?RiskService $riskService = null,
        protected ?WebhookService $webhookService = null,
        protected ?PayNotifyService $payNotifyService = null
    ) {
        $this->platformService = $platformService ?? new PlatformService();
        $this->ledgerService = $ledgerService ?? new LedgerService();
        $this->riskService = $riskService ?? new RiskService();
        $this->webhookService = $webhookService ?? new WebhookService();
        $this->payNotifyService = $payNotifyService ?? new PayNotifyService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = WithdrawOrderModel::query()->with(['merchant:id,name,merchant_no', 'platform:id,code,name']);
        foreach (['status', 'merchant_id', 'order_no', 'out_trade_no', 'tx_hash'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to'] . ' 23:59:59');
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn (WithdrawOrderModel $row) => $this->formatOrder($row));

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $order = WithdrawOrderModel::with(['merchant:id,name,merchant_no', 'platform:id,code,name'])->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }

        return $this->formatOrder($order);
    }

    public function create(MerchantModel $merchant, array $data): array
    {
        $outTradeNo = trim((string) ($data['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'out_trade_no 不能为空');
        }

        $existing = WithdrawOrderModel::where('merchant_id', $merchant->id)
            ->where('out_trade_no', $outTradeNo)
            ->first();
        if ($existing) {
            return $this->formatOrder($existing);
        }

        $platform = $this->platformService->getByCode((string) ($data['platform_code'] ?? ''));
        $amount = Decimal::format((string) ($data['amount'] ?? '0'));
        $toAddress = trim((string) ($data['to_address'] ?? ''));

        $this->riskService->validateAmount($platform, $amount, 'withdraw');
        $this->riskService->validateWithdrawAddress((string) $platform->chain, $toAddress);

        $feeAmount = $this->riskService->calcWithdrawFee(
            $amount,
            (string) $merchant->withdraw_fee_rate,
            (string) $merchant->withdraw_fee_min,
            (string) $merchant->withdraw_fee_max
        );
        $payoutAmount = Decimal::sub($amount, $feeAmount);

        $autoMax = Decimal::format((string) $merchant->auto_withdraw_max);
        $needReview = Decimal::cmp($autoMax, '0') <= 0 || Decimal::cmp($amount, $autoMax) > 0;
        $status = $needReview ? WithdrawOrderModel::STATUS_REVIEWING : WithdrawOrderModel::STATUS_APPROVED;

        $orderNo = OrderNoGenerator::withdraw();
        $order = null;
        DB::connection()->transaction(function () use (
            &$order,
            $outTradeNo,
            $merchant,
            $platform,
            $amount,
            $feeAmount,
            $payoutAmount,
            $toAddress,
            $status,
            $orderNo,
            $data
        ) {
            $order = WithdrawOrderModel::create([
                'order_no' => $orderNo,
                'out_trade_no' => $outTradeNo,
                'merchant_id' => $merchant->id,
                'platform_id' => $platform->id,
                'chain' => $platform->chain,
                'currency' => $platform->currency,
                'withdraw_amount' => $amount,
                'fee_amount' => $feeAmount,
                'payout_amount' => $payoutAmount,
                'to_address' => $toAddress,
                'status' => $status,
                'notify_url' => (string) ($data['notify_url'] ?? $merchant->notify_url),
                'notify_status' => 'pending',
                'extra' => $data['extra'] ?? null,
            ]);

            $this->ledgerService->freezeWithdraw(
                $merchant->id,
                (string) $platform->currency,
                (string) $platform->chain,
                $amount,
                (int) $order->id,
                $orderNo
            );
        });

        if (!$order) {
            // 理论上不会发生（transaction 里抛异常会直接中断），这里只是兜底
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '出金单创建失败');
        }

        if ($status === WithdrawOrderModel::STATUS_APPROVED) {
            Redis::send(WithdrawBroadcastConsumer::QUEUE_NAME, ['withdraw_id' => $order->id]);
        } else {
            $this->webhookService->dispatchWithdraw($order, 'withdraw.reviewing');
            $this->payNotifyService->withdrawReviewing($order);
        }

        return $this->formatOrder($order);
    }

    public function findForMerchant(MerchantModel $merchant, ?string $orderNo, ?string $outTradeNo): array
    {
        $query = WithdrawOrderModel::where('merchant_id', $merchant->id);
        if ($orderNo) {
            $query->where('order_no', $orderNo);
        } elseif ($outTradeNo) {
            $query->where('out_trade_no', $outTradeNo);
        } else {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请提供 order_no 或 out_trade_no');
        }
        $order = $query->first();
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }

        return $this->formatOrder($order);
    }

    public function approve(int $id, int $reviewerId): array
    {
        $order = WithdrawOrderModel::find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }
        if ($order->status !== WithdrawOrderModel::STATUS_REVIEWING) {
            throw new BusinessException(ErrorCode::PAY_ORDER_STATUS_INVALID);
        }

        $order->update([
            'status' => WithdrawOrderModel::STATUS_APPROVED,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        Redis::send(WithdrawBroadcastConsumer::QUEUE_NAME, ['withdraw_id' => $order->id]);

        return $this->formatOrder($order->fresh());
    }

    public function reject(int $id, int $reviewerId, string $reason): array
    {
        $order = WithdrawOrderModel::find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }
        if ($order->status !== WithdrawOrderModel::STATUS_REVIEWING) {
            throw new BusinessException(ErrorCode::PAY_ORDER_STATUS_INVALID);
        }

        $this->ledgerService->unfreezeWithdraw(
            (int) $order->merchant_id,
            (string) $order->currency,
            (string) $order->chain,
            (string) $order->withdraw_amount,
            (int) $order->id,
            (string) $order->order_no,
            '审核驳回解冻'
        );

        $order->update([
            'status' => WithdrawOrderModel::STATUS_REJECTED,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reject_reason' => $reason,
        ]);

        $this->webhookService->dispatchWithdraw($order->fresh(), 'withdraw.rejected');

        return $this->formatOrder($order->fresh());
    }

    public function retryBroadcast(int $id): array
    {
        $order = WithdrawOrderModel::find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }
        if (!in_array($order->status, [WithdrawOrderModel::STATUS_FAILED, WithdrawOrderModel::STATUS_APPROVED], true)) {
            throw new BusinessException(ErrorCode::PAY_ORDER_STATUS_INVALID);
        }

        $order->update(['status' => WithdrawOrderModel::STATUS_APPROVED, 'remark' => '']);
        Redis::send(WithdrawBroadcastConsumer::QUEUE_NAME, ['withdraw_id' => $order->id]);

        return $this->formatOrder($order->fresh());
    }

    public function broadcast(int $withdrawId): void
    {
        $order = WithdrawOrderModel::find($withdrawId);
        if (!$order || !in_array($order->status, [WithdrawOrderModel::STATUS_APPROVED, WithdrawOrderModel::STATUS_FAILED], true)) {
            return;
        }

        if (!HotWalletConfig::isConfigured()) {
            $this->failBroadcast($order, '热钱包未配置（TRON_HOT_WALLET_ADDRESS + 私钥）');
            return;
        }

        $trxMin = (string) env('PAY_HOT_WALLET_TRX_MIN', '0');
        if ($trxMin !== '' && Decimal::cmp($trxMin, '0') > 0) {
            $adapter = ChainFactory::make((string) $order->chain);
            if ($adapter instanceof \app\support\chain\TronAdapter) {
                $trxBalance = $adapter->getTrxBalance(HotWalletConfig::address());
                if (Decimal::cmp($trxBalance, $trxMin) < 0) {
                    $this->failBroadcast($order, "热钱包 TRX 不足（当前 {$trxBalance}，需要 {$trxMin}）");
                    return;
                }
            }
        }

        try {
            $order->update(['status' => WithdrawOrderModel::STATUS_PAYING, 'paid_at' => date('Y-m-d H:i:s')]);
            $this->webhookService->dispatchWithdraw($order->fresh(), 'withdraw.paying');

            $adapter = ChainFactory::make((string) $order->chain);
            $txHash = $adapter->broadcastUsdtTransfer(
                HotWalletConfig::privateKey(),
                (string) $order->to_address,
                (string) $order->payout_amount
            );

            $order->update(['tx_hash' => $txHash]);

            ChainTransactionModel::create([
                'chain' => $order->chain,
                'tx_hash' => $txHash,
                'log_index' => 0,
                'from_address' => HotWalletConfig::address(),
                'to_address' => $order->to_address,
                'amount' => $order->payout_amount,
                'token_contract' => env('TRON_USDT_CONTRACT', ''),
                'biz_type' => ChainTransactionModel::BIZ_WITHDRAW,
                'biz_id' => $order->id,
                'confirmations' => 0,
                'status' => 'detected',
            ]);
        } catch (\Throwable $e) {
            $this->failBroadcast($order, $e->getMessage());
        }
    }

    public function checkConfirmations(): int
    {
        $count = 0;
        WithdrawOrderModel::query()
            ->where('status', WithdrawOrderModel::STATUS_PAYING)
            ->where('tx_hash', '!=', '')
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    if ($this->checkSingleConfirmation($order)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function checkSingleConfirmation(WithdrawOrderModel $order): bool
    {
        $platform = PlatformModel::find($order->platform_id);
        if (!$platform) {
            return false;
        }

        $adapter = ChainFactory::make((string) $order->chain);
        $confirmations = $adapter->getConfirmations((string) $order->tx_hash);
        $order->update(['confirmations' => $confirmations]);

        if ($confirmations < (int) $platform->withdraw_confirmations) {
            return false;
        }

        $this->ledgerService->confirmWithdraw(
            (int) $order->merchant_id,
            (string) $order->currency,
            (string) $order->chain,
            (string) $order->withdraw_amount,
            (int) $order->id,
            (string) $order->order_no
        );

        $order->update([
            'status' => WithdrawOrderModel::STATUS_SUCCESS,
            'succeeded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->webhookService->dispatchWithdraw($order->fresh(), 'withdraw.success');
        $this->payNotifyService->withdrawSuccess($order->fresh());

        return true;
    }

    private function failBroadcast(WithdrawOrderModel $order, string $message): void
    {
        $order->update(['status' => WithdrawOrderModel::STATUS_FAILED, 'remark' => $message]);
        $this->ledgerService->unfreezeWithdraw(
            (int) $order->merchant_id,
            (string) $order->currency,
            (string) $order->chain,
            (string) $order->withdraw_amount,
            (int) $order->id,
            (string) $order->order_no,
            '广播失败解冻'
        );
        $this->webhookService->dispatchWithdraw($order->fresh(), 'withdraw.failed');
    }

    public function formatOrder(WithdrawOrderModel $order): array
    {
        $data = $order->toArray();
        $data['withdraw_amount'] = Decimal::format((string) $order->withdraw_amount);
        $data['fee_amount'] = Decimal::format((string) $order->fee_amount);
        $data['payout_amount'] = Decimal::format((string) $order->payout_amount);

        return $data;
    }
}
