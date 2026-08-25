<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\service\pay\WithdrawOrderService;
use Webman\RedisQueue\Consumer;

class WithdrawBroadcastConsumer implements Consumer
{
    public const QUEUE_NAME = 'pay_withdraw_broadcast';

    public $queue = self::QUEUE_NAME;

    public $connection = 'default';

    public function consume($data): void
    {
        $withdrawId = (int) ($data['withdraw_id'] ?? 0);
        if ($withdrawId <= 0) {
            return;
        }

        (new WithdrawOrderService())->broadcast($withdrawId);
    }
}
