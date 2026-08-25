<?php

declare(strict_types=1);

use app\process\RedisQueueConsumer;
use app\queue\redis\CsvExportConsumer;
use app\queue\redis\WithdrawBroadcastConsumer;
use app\queue\redis\WebhookRetryConsumer;
use app\queue\redis\CollectionConsumer;

$consumerDir = app_path() . '/queue/redis';

return [
    'export' => [
        'handler' => RedisQueueConsumer::class,
        'count' => 1,
        'constructor' => [
            $consumerDir,
            [CsvExportConsumer::QUEUE_NAME],
        ],
    ],
    'pay' => [
        'handler' => RedisQueueConsumer::class,
        'count' => 2,
        'constructor' => [
            $consumerDir,
            [WithdrawBroadcastConsumer::QUEUE_NAME, WebhookRetryConsumer::QUEUE_NAME, CollectionConsumer::QUEUE_NAME],
        ],
    ],
];
