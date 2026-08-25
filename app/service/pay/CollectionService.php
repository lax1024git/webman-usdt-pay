<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\ChainTransactionModel;
use app\model\pay\CollectionTaskModel;
use app\model\pay\DepositOrderModel;
use app\model\pay\WalletAddressModel;
use app\queue\redis\CollectionConsumer;
use app\support\chain\ChainFactory;
use app\support\Decimal;
use app\support\ErrorCode;
use app\support\pay\HotWalletConfig;
use Webman\RedisQueue\Redis;

class CollectionService
{
    public function __construct(
        protected ?WalletService $walletService = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = CollectionTaskModel::query();
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from_address'])) {
            $query->where('from_address', 'like', '%' . $filters['from_address'] . '%');
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * 扫描可归集地址并入队。
     */
    public function trigger(int $platformId = 0): int
    {
        if (!filter_var(env('PAY_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }

        $hot = HotWalletConfig::address();
        if ($hot === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '未配置热钱包地址，无法归集');
        }

        $min = Decimal::format((string) env('PAY_COLLECTION_MIN', '1'));
        $adapter = ChainFactory::make('TRC20');
        $count = 0;

        $query = WalletAddressModel::query()
            ->where('type', WalletAddressModel::TYPE_DEPOSIT)
            ->whereIn('status', [
                WalletAddressModel::STATUS_ASSIGNED,
                WalletAddressModel::STATUS_AVAILABLE,
            ]);
        if ($platformId > 0) {
            $query->where('platform_id', $platformId);
        }

        $query->orderBy('id')->chunkById(50, function ($rows) use ($hot, $min, $adapter, &$count) {
            foreach ($rows as $row) {
                if (strcasecmp((string) $row->address, $hot) === 0) {
                    continue;
                }
                if ($this->hasOpenDeposit((int) $row->id, (string) $row->address)) {
                    continue;
                }
                $busy = CollectionTaskModel::query()
                    ->where('wallet_address_id', $row->id)
                    ->whereIn('status', [CollectionTaskModel::STATUS_PENDING, CollectionTaskModel::STATUS_BROADCASTING])
                    ->exists();
                if ($busy) {
                    continue;
                }

                try {
                    $balance = $adapter->getUsdtBalance((string) $row->address);
                } catch (\Throwable) {
                    continue;
                }
                $row->update([
                    'balance' => $balance,
                    'balance_synced_at' => date('Y-m-d H:i:s'),
                ]);
                if (Decimal::cmp($balance, $min) < 0) {
                    continue;
                }

                $task = CollectionTaskModel::create([
                    'platform_id' => (int) $row->platform_id,
                    'wallet_address_id' => (int) $row->id,
                    'from_address' => $row->address,
                    'to_address' => $hot,
                    'amount' => $balance,
                    'status' => CollectionTaskModel::STATUS_PENDING,
                ]);
                Redis::send(CollectionConsumer::QUEUE_NAME, ['task_id' => $task->id]);
                $count++;
            }
        });

        return $count;
    }

    public function retry(int $id): void
    {
        $task = CollectionTaskModel::find($id);
        if (!$task) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '归集任务不存在');
        }
        if ((string) $task->status === CollectionTaskModel::STATUS_SUCCESS) {
            throw new BusinessException(ErrorCode::PAY_ORDER_STATUS_INVALID, '任务已成功');
        }
        $task->update([
            'status' => CollectionTaskModel::STATUS_PENDING,
            'error_message' => '',
        ]);
        Redis::send(CollectionConsumer::QUEUE_NAME, ['task_id' => $task->id]);
    }

    public function broadcast(int $taskId): void
    {
        $task = CollectionTaskModel::find($taskId);
        if (!$task || !in_array((string) $task->status, [CollectionTaskModel::STATUS_PENDING, CollectionTaskModel::STATUS_FAILED], true)) {
            return;
        }

        $wallet = WalletAddressModel::find($task->wallet_address_id);
        if (!$wallet) {
            $task->update([
                'status' => CollectionTaskModel::STATUS_FAILED,
                'error_message' => '地址记录不存在',
            ]);
            return;
        }

        $privateKey = $this->walletService->resolvePrivateKey($wallet);
        if ($privateKey === '') {
            $task->update([
                'status' => CollectionTaskModel::STATUS_FAILED,
                'error_message' => '无法解析入金地址私钥（需配置 HD）',
            ]);
            return;
        }

        $task->update(['status' => CollectionTaskModel::STATUS_BROADCASTING]);
        try {
            $adapter = ChainFactory::make('TRC20');
            $txHash = $adapter->broadcastUsdtTransfer(
                $privateKey,
                (string) $task->to_address,
                Decimal::format((string) $task->amount)
            );
            $task->update([
                'status' => CollectionTaskModel::STATUS_SUCCESS,
                'tx_hash' => $txHash,
                'error_message' => '',
            ]);
            $wallet->update(['status' => WalletAddressModel::STATUS_COLLECTED]);
            ChainTransactionModel::create([
                'chain' => 'TRC20',
                'tx_hash' => $txHash,
                'log_index' => 0,
                'block_number' => 0,
                'from_address' => $task->from_address,
                'to_address' => $task->to_address,
                'amount' => $task->amount,
                'token_contract' => (string) env('TRON_USDT_CONTRACT', ''),
                'biz_type' => ChainTransactionModel::BIZ_COLLECTION,
                'biz_id' => $task->id,
                'confirmations' => 0,
                'status' => 'detected',
            ]);
        } catch (\Throwable $e) {
            $task->update([
                'status' => CollectionTaskModel::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    private function hasOpenDeposit(int $walletAddressId, string $address): bool
    {
        return DepositOrderModel::query()
            ->where(function ($q) use ($walletAddressId, $address) {
                $q->where('wallet_address_id', $walletAddressId)
                    ->orWhere('deposit_address', $address);
            })
            ->whereIn('status', [DepositOrderModel::STATUS_PENDING, DepositOrderModel::STATUS_DETECTING])
            ->exists();
    }
}
