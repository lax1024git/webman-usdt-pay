<?php

declare(strict_types=1);

namespace app\service\pay;

use app\model\pay\WalletAddressModel;
use app\support\chain\ChainFactory;
use app\support\chain\TronHdWallet;
use app\support\pay\HotWalletConfig;
use app\support\pay\PaySecretCipher;
use Illuminate\Database\Capsule\Manager as DB;

class WalletService
{
    /**
     * 分配入金地址。
     * - 优先复用 STATUS_AVAILABLE 池地址（一单一址）
     * - 否则派生；当前 TronAdapter 在未配置 HD 时返回热钱包地址（共享模式）
     * - 共享热钱包地址只建一条记录，避免 uk_wallet_address 冲突
     *
     * @return array{id:int,address:string}
     */
    public function allocateDepositAddress(int $platformId, int $orderId): array
    {
        $existing = WalletAddressModel::query()
            ->where('order_id', $orderId)
            ->where('type', WalletAddressModel::TYPE_DEPOSIT)
            ->first();
        if ($existing) {
            return ['id' => (int) $existing->id, 'address' => (string) $existing->address];
        }

        return DB::connection()->transaction(function () use ($platformId, $orderId) {
            $pooled = WalletAddressModel::query()
                ->where('platform_id', $platformId)
                ->where('type', WalletAddressModel::TYPE_DEPOSIT)
                ->where('status', WalletAddressModel::STATUS_AVAILABLE)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($pooled) {
                $pooled->update([
                    'order_id' => $orderId,
                    'status' => WalletAddressModel::STATUS_ASSIGNED,
                ]);

                return ['id' => (int) $pooled->id, 'address' => (string) $pooled->address];
            }

            if (TronHdWallet::isConfigured()) {
                $index = $this->nextDerivationIndex();
                $keypair = TronHdWallet::derive($index);
                $row = WalletAddressModel::create([
                    'platform_id' => $platformId,
                    'address' => $keypair['address'],
                    'type' => WalletAddressModel::TYPE_DEPOSIT,
                    'derivation_index' => $index,
                    'order_id' => $orderId,
                    'encrypted_key' => PaySecretCipher::encrypt($keypair['private_key']),
                    'balance' => '0.000000',
                    'status' => WalletAddressModel::STATUS_ASSIGNED,
                ]);

                return ['id' => (int) $row->id, 'address' => (string) $row->address];
            }

            $adapter = ChainFactory::make('TRC20');
            $address = $adapter->deriveDepositAddress($orderId);

            $shared = WalletAddressModel::query()
                ->where('address', $address)
                ->lockForUpdate()
                ->first();

            if ($shared) {
                // 共享热钱包：多订单共用同一地址行，不改 status / 不撞唯一索引
                if ((int) $shared->order_id === 0) {
                    $shared->update(['order_id' => $orderId]);
                }

                return ['id' => (int) $shared->id, 'address' => (string) $shared->address];
            }

            $row = WalletAddressModel::create([
                'platform_id' => $platformId,
                'address' => $address,
                'type' => WalletAddressModel::TYPE_DEPOSIT,
                'derivation_index' => $orderId,
                'order_id' => $orderId,
                'balance' => '0.000000',
                'status' => WalletAddressModel::STATUS_ASSIGNED,
            ]);

            return ['id' => (int) $row->id, 'address' => (string) $row->address];
        });
    }

    /**
     * 订单结束后尝试回收地址（共享热钱包不回收）。
     */
    public function releaseDepositAddress(int $walletAddressId, int $orderId): void
    {
        if ($walletAddressId <= 0) {
            return;
        }

        $row = WalletAddressModel::find($walletAddressId);
        if (!$row || (string) $row->type !== WalletAddressModel::TYPE_DEPOSIT) {
            return;
        }

        $hot = HotWalletConfig::address();
        if ($hot !== '' && strcasecmp((string) $row->address, $hot) === 0) {
            return;
        }

        if ((int) $row->order_id !== $orderId) {
            return;
        }

        $row->update([
            'order_id' => 0,
            'status' => WalletAddressModel::STATUS_AVAILABLE,
        ]);
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = WalletAddressModel::query();
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['address'])) {
            $query->where('address', 'like', '%' . $filters['address'] . '%');
        }
        if (!empty($filters['platform_id'])) {
            $query->where('platform_id', (int) $filters['platform_id']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    /** @return array<string, mixed> */
    public function hotWalletStatus(): array
    {
        $address = HotWalletConfig::address();
        $usdtMin = (string) env('PAY_HOT_WALLET_USDT_MIN', '0');
        $trxMin = (string) env('PAY_HOT_WALLET_TRX_MIN', '0');

        if ($address === '') {
            return [
                'configured' => false,
                'address' => '',
                'usdt_balance' => '0.000000',
                'trx_balance' => '0.000000',
                'usdt_min' => $usdtMin,
                'trx_min' => $trxMin,
                'usdt_low' => false,
                'trx_low' => false,
            ];
        }

        $adapter = ChainFactory::make('TRC20');
        $trxBalance = '0.000000';
        if ($adapter instanceof \app\support\chain\TronAdapter) {
            $trxBalance = $adapter->getTrxBalance($address);
        }
        $usdtBalance = $adapter->getUsdtBalance($address);

        return [
            'configured' => HotWalletConfig::isConfigured(),
            'address' => $address,
            'usdt_balance' => $usdtBalance,
            'trx_balance' => $trxBalance,
            'usdt_min' => $usdtMin,
            'trx_min' => $trxMin,
            'usdt_low' => $usdtMin !== '' && \app\support\Decimal::cmp($usdtBalance, $usdtMin) < 0,
            'trx_low' => $trxMin !== '' && \app\support\Decimal::cmp($trxBalance, $trxMin) < 0,
        ];
    }

    /**
     * 检查热钱包余额并写入后台通知（去抖：同类告警 30 分钟内不重复）。
     */
    public function monitorHotWallet(): array
    {
        $status = $this->hotWalletStatus();
        if (!$status['configured']) {
            return $status;
        }

        $alerts = [];
        if (!empty($status['usdt_low'])) {
            $alerts[] = [
                'key' => 'pay_hot_usdt_low',
                'title' => '热钱包 USDT 余额不足',
                'content' => sprintf(
                    '当前 %s USDT，低于阈值 %s',
                    $status['usdt_balance'],
                    $status['usdt_min']
                ),
            ];
        }
        if (!empty($status['trx_low'])) {
            $alerts[] = [
                'key' => 'pay_hot_trx_low',
                'title' => '热钱包 TRX 余额不足',
                'content' => sprintf(
                    '当前 %s TRX，低于阈值 %s（可能影响出金能量）',
                    $status['trx_balance'],
                    $status['trx_min']
                ),
            ];
        }

        $notify = new PayNotifyService();
        foreach ($alerts as $alert) {
            $notify->hotWalletAlert($alert['key'], $alert['title'], $alert['content']);
        }

        $status['alerts'] = array_column($alerts, 'title');

        return $status;
    }

    public function resolvePrivateKey(WalletAddressModel $row): string
    {
        $encrypted = (string) ($row->getAttribute('encrypted_key') ?? '');
        if ($encrypted !== '') {
            $plain = PaySecretCipher::decrypt($encrypted);
            if ($plain !== '') {
                return $plain;
            }
        }

        $index = (int) $row->derivation_index;
        if (TronHdWallet::isConfigured() && $index > 0) {
            $derived = TronHdWallet::derive($index);
            if (strcasecmp($derived['address'], (string) $row->address) === 0) {
                return $derived['private_key'];
            }
        }

        return '';
    }

    private function nextDerivationIndex(): int
    {
        $max = (int) WalletAddressModel::query()
            ->where('type', WalletAddressModel::TYPE_DEPOSIT)
            ->max('derivation_index');

        return max(1, $max + 1);
    }
}
