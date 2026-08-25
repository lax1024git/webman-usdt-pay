<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\ChainTransactionModel;
use app\model\pay\DepositOrderModel;
use app\model\pay\MerchantModel;
use app\model\pay\PlatformModel;
use app\support\Decimal;
use app\support\ErrorCode;
use app\support\chain\ChainFactory;
use app\support\pay\OrderNoGenerator;
use Illuminate\Database\Capsule\Manager as DB;

class DepositOrderService
{
    public function __construct(
        protected ?PlatformService $platformService = null,
        protected ?WalletService $walletService = null,
        protected ?LedgerService $ledgerService = null,
        protected ?RiskService $riskService = null,
        protected ?WebhookService $webhookService = null,
        protected ?PayNotifyService $payNotifyService = null
    ) {
        $this->platformService = $platformService ?? new PlatformService();
        $this->walletService = $walletService ?? new WalletService();
        $this->ledgerService = $ledgerService ?? new LedgerService();
        $this->riskService = $riskService ?? new RiskService();
        $this->webhookService = $webhookService ?? new WebhookService();
        $this->payNotifyService = $payNotifyService ?? new PayNotifyService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = DepositOrderModel::query()->with(['merchant:id,name,merchant_no', 'platform:id,code,name']);
        $this->applyFilters($query, $filters);
        $total = $query->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn (DepositOrderModel $row) => $this->formatOrder($row));

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $order = DepositOrderModel::with(['merchant:id,name,merchant_no', 'platform:id,code,name'])->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }

        $data = $this->formatOrder($order);
        $data['chain_tx'] = ChainTransactionModel::query()
            ->where('biz_type', ChainTransactionModel::BIZ_DEPOSIT)
            ->where('biz_id', $order->id)
            ->orderByDesc('id')
            ->get();

        return $data;
    }

    public function create(MerchantModel $merchant, array $data): array
    {
        $outTradeNo = trim((string) ($data['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'out_trade_no 不能为空');
        }

        $existing = DepositOrderModel::where('merchant_id', $merchant->id)
            ->where('out_trade_no', $outTradeNo)
            ->first();
        if ($existing) {
            return $this->formatOrder($existing);
        }

        $platform = $this->platformService->getByCode((string) ($data['platform_code'] ?? ''));
        $amount = Decimal::format((string) ($data['amount'] ?? '0'));
        $this->riskService->validateAmount($platform, $amount, 'deposit');

        $feeRate = (string) ($merchant->deposit_fee_rate ?? '0');
        $feeAmount = Decimal::format(Decimal::mul($amount, $feeRate));
        $netAmount = Decimal::sub($amount, $feeAmount);
        $expireSeconds = (int) $platform->deposit_expire_seconds ?: 1800;

        $order = DepositOrderModel::create([
            'order_no' => OrderNoGenerator::deposit(),
            'out_trade_no' => $outTradeNo,
            'merchant_id' => $merchant->id,
            'platform_id' => $platform->id,
            'chain' => $platform->chain,
            'currency' => $platform->currency,
            'amount' => $amount,
            'paid_amount' => '0.000000',
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'deposit_address' => '',
            'wallet_address_id' => 0,
            'status' => DepositOrderModel::STATUS_PENDING,
            'notify_url' => (string) ($data['notify_url'] ?? $merchant->notify_url),
            'notify_status' => 'pending',
            'expired_at' => date('Y-m-d H:i:s', time() + $expireSeconds),
            'extra' => $data['extra'] ?? null,
        ]);

        $wallet = $this->walletService->allocateDepositAddress((int) $platform->id, (int) $order->id);
        $order->update([
            'deposit_address' => $wallet['address'],
            'wallet_address_id' => $wallet['id'],
        ]);

        return $this->formatOrder($order->fresh());
    }

    public function findForMerchant(MerchantModel $merchant, ?string $orderNo, ?string $outTradeNo): array
    {
        $query = DepositOrderModel::where('merchant_id', $merchant->id);
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

    public function expirePendingOrders(): int
    {
        $count = 0;
        DepositOrderModel::query()
            ->where('status', DepositOrderModel::STATUS_PENDING)
            ->where('expired_at', '<', date('Y-m-d H:i:s'))
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $order->update(['status' => DepositOrderModel::STATUS_EXPIRED]);
                    $this->walletService->releaseDepositAddress(
                        (int) $order->wallet_address_id,
                        (int) $order->id
                    );
                    $this->webhookService->dispatchDeposit($order->fresh(), 'deposit.expired');
                    $count++;
                }
            });

        return $count;
    }

    public function scanPendingDeposits(): int
    {
        $count = 0;
        $adapter = ChainFactory::make('TRC20');

        DepositOrderModel::query()
            ->whereIn('status', [DepositOrderModel::STATUS_PENDING, DepositOrderModel::STATUS_DETECTING])
            ->where('deposit_address', '!=', '')
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$count, $adapter) {
                foreach ($orders as $order) {
                    if ($this->scanSingleOrder($order, $adapter)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function checkConfirmations(): int
    {
        $count = 0;
        $adapter = ChainFactory::make('TRC20');

        DepositOrderModel::query()
            ->where('status', DepositOrderModel::STATUS_DETECTING)
            ->where('tx_hash', '!=', '')
            ->chunkById(50, function ($orders) use (&$count, $adapter) {
                foreach ($orders as $order) {
                    $platform = PlatformModel::find($order->platform_id);
                    if (!$platform) {
                        continue;
                    }
                    $confirmations = $adapter->getConfirmations((string) $order->tx_hash);
                    $order->update(['confirmations' => $confirmations]);
                    if ($confirmations >= (int) $platform->deposit_confirmations) {
                        $this->markSuccess($order->fresh());
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function manualCredit(int $id, string $paidAmount, ?string $txHash = null): array
    {
        $order = DepositOrderModel::find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::PAY_ORDER_NOT_FOUND);
        }
        if (!in_array($order->status, [DepositOrderModel::STATUS_PENDING, DepositOrderModel::STATUS_MANUAL, DepositOrderModel::STATUS_DETECTING], true)) {
            throw new BusinessException(ErrorCode::PAY_ORDER_STATUS_INVALID);
        }

        $paidAmount = Decimal::format($paidAmount);
        $feeAmount = Decimal::format(Decimal::mul($paidAmount, (string) MerchantModel::find($order->merchant_id)?->deposit_fee_rate ?? '0'));
        $netAmount = Decimal::sub($paidAmount, $feeAmount);

        $order->update([
            'paid_amount' => $paidAmount,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'tx_hash' => $txHash ?? $order->tx_hash,
            'status' => DepositOrderModel::STATUS_SUCCESS,
            'paid_at' => date('Y-m-d H:i:s'),
            'succeeded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->ledgerService->creditDeposit(
            (int) $order->merchant_id,
            (string) $order->currency,
            (string) $order->chain,
            $netAmount,
            (int) $order->id,
            (string) $order->order_no,
            '人工补单入账'
        );
        $this->webhookService->dispatchDeposit($order->fresh(), 'deposit.success');

        return $this->formatOrder($order->fresh());
    }

    private function scanSingleOrder(DepositOrderModel $order, $adapter): bool
    {
        $transfers = $adapter->fetchIncomingTransfers((string) $order->deposit_address);
        foreach ($transfers as $tx) {
            if (!$this->matchAmount($order, $tx['amount'])) {
                continue;
            }
            if (ChainTransactionModel::where('chain', $order->chain)
                ->where('tx_hash', $tx['tx_hash'])
                ->where('log_index', $tx['log_index'])
                ->exists()) {
                continue;
            }

            DB::connection()->transaction(function () use ($order, $tx) {
                ChainTransactionModel::create([
                    'chain' => $order->chain,
                    'tx_hash' => $tx['tx_hash'],
                    'log_index' => $tx['log_index'],
                    'block_number' => $tx['block_number'],
                    'from_address' => $tx['from'],
                    'to_address' => $tx['to'],
                    'amount' => $tx['amount'],
                    'token_contract' => env('TRON_USDT_CONTRACT', ''),
                    'biz_type' => ChainTransactionModel::BIZ_DEPOSIT,
                    'biz_id' => $order->id,
                    'confirmations' => $tx['confirmations'],
                    'status' => 'detected',
                ]);

                $order->update([
                    'status' => DepositOrderModel::STATUS_DETECTING,
                    'tx_hash' => $tx['tx_hash'],
                    'from_address' => $tx['from'],
                    'paid_amount' => $tx['amount'],
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
            });

            $this->webhookService->dispatchDeposit($order->fresh(), 'deposit.detecting');
            $this->payNotifyService->depositReviewing($order->fresh());

            $platform = PlatformModel::find($order->platform_id);
            $confirmations = $adapter->getConfirmations($tx['tx_hash']);
            if ($platform && $confirmations >= (int) $platform->deposit_confirmations) {
                $this->markSuccess($order->fresh());
            }

            return true;
        }

        return false;
    }

    private function markSuccess(DepositOrderModel $order): void
    {
        if ($order->status === DepositOrderModel::STATUS_SUCCESS) {
            return;
        }

        $paid = Decimal::format((string) $order->paid_amount);
        $merchant = MerchantModel::find($order->merchant_id);
        $feeRate = (string) ($merchant?->deposit_fee_rate ?? '0');
        $feeAmount = Decimal::format(Decimal::mul($paid, $feeRate));
        $netAmount = Decimal::sub($paid, $feeAmount);

        $order->update([
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'status' => DepositOrderModel::STATUS_SUCCESS,
            'succeeded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->ledgerService->creditDeposit(
            (int) $order->merchant_id,
            (string) $order->currency,
            (string) $order->chain,
            $netAmount,
            (int) $order->id,
            (string) $order->order_no
        );
        $this->walletService->releaseDepositAddress(
            (int) $order->wallet_address_id,
            (int) $order->id
        );
        $this->webhookService->dispatchDeposit($order->fresh(), 'deposit.success');
        $this->payNotifyService->depositSuccess($order->fresh());
    }

    private function matchAmount(DepositOrderModel $order, string $paidAmount): bool
    {
        $platform = PlatformModel::find($order->platform_id);
        $mode = $platform?->amount_match_mode ?? PlatformModel::AMOUNT_MATCH_EXACT;

        return match ($mode) {
            PlatformModel::AMOUNT_MATCH_ACTUAL => Decimal::cmp($paidAmount, '0') > 0,
            PlatformModel::AMOUNT_MATCH_TOLERANT => Decimal::cmp($paidAmount, Decimal::mul((string) $order->amount, '0.99')) >= 0,
            default => Decimal::cmp($paidAmount, (string) $order->amount) === 0,
        };
    }

    private function applyFilters($query, array $filters): void
    {
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
    }

    public function formatOrder(DepositOrderModel $order): array
    {
        $data = $order->toArray();
        $data['amount'] = Decimal::format((string) $order->amount);
        $data['paid_amount'] = Decimal::format((string) $order->paid_amount);
        $data['fee_amount'] = Decimal::format((string) $order->fee_amount);
        $data['net_amount'] = Decimal::format((string) $order->net_amount);
        $data['qr_content'] = $order->deposit_address;

        return $data;
    }
}
