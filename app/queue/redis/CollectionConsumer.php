<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\service\pay\CollectionService;
use Webman\RedisQueue\Consumer;

class CollectionConsumer implements Consumer
{
    public const QUEUE_NAME = 'pay_collection';

    public $queue = self::QUEUE_NAME;

    public $connection = 'default';

    public function consume($data): void
    {
        $taskId = (int) ($data['task_id'] ?? 0);
        if ($taskId <= 0) {
            return;
        }

        (new CollectionService())->broadcast($taskId);
    }
}
