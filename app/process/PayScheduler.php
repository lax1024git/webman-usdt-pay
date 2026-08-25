<?php

declare(strict_types=1);

namespace app\process;

use app\service\pay\CollectionService;
use app\service\pay\DepositOrderService;
use app\service\pay\WalletService;
use app\service\pay\WebhookService;
use app\service\pay\WithdrawOrderService;
use Workerman\Crontab\Crontab;
use Workerman\Worker;

/**
 * USDT 充付定时任务：链上扫描、确认数更新、订单过期、回调补偿、热钱包告警。
 */
class PayScheduler
{
    public function onWorkerStart(Worker $worker): void
    {
        if ((int) $worker->id !== 0) {
            return;
        }

        $scanInterval = max(10, (int) env('PAY_SCAN_INTERVAL', 30));
        $enabled = filter_var(env('PAY_SCHEDULER_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }

        // 每 N 秒扫描入金 + 更新确认数
        new Crontab("*/{$scanInterval} * * * * *", function (): void {
            try {
                $depositService = new DepositOrderService();
                $depositService->scanPendingDeposits();
                $depositService->checkConfirmations();
            } catch (\Throwable $e) {
                echo '[PayScheduler] scan error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        // 每分钟过期未付入金单
        new Crontab('0 * * * * *', function (): void {
            try {
                (new DepositOrderService())->expirePendingOrders();
            } catch (\Throwable $e) {
                echo '[PayScheduler] expire error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        // 每 30 秒检查出金确认
        new Crontab('*/30 * * * * *', function (): void {
            try {
                (new WithdrawOrderService())->checkConfirmations();
            } catch (\Throwable $e) {
                echo '[PayScheduler] withdraw confirm error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        // 每 5 分钟补偿失败回调
        new Crontab('0 */5 * * * *', function (): void {
            try {
                $count = (new WebhookService())->compensateFailed();
                if ($count > 0) {
                    echo '[PayScheduler] webhook compensate queued: ' . $count . PHP_EOL;
                }
            } catch (\Throwable $e) {
                echo '[PayScheduler] webhook compensate error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        // 每 5 分钟热钱包余额告警
        new Crontab('30 */5 * * * *', function (): void {
            try {
                (new WalletService())->monitorHotWallet();
            } catch (\Throwable $e) {
                echo '[PayScheduler] hot wallet monitor error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        // 每 10 分钟扫描归集
        new Crontab('0 */10 * * * *', function (): void {
            try {
                $count = (new CollectionService())->trigger();
                if ($count > 0) {
                    echo '[PayScheduler] collection queued: ' . $count . PHP_EOL;
                }
            } catch (\Throwable $e) {
                echo '[PayScheduler] collection error: ' . $e->getMessage() . PHP_EOL;
            }
        });

        echo '[PayScheduler] started (scan every ' . $scanInterval . 's)' . PHP_EOL;
    }
}
