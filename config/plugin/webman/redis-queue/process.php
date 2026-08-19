<?php

declare(strict_types=1);

use app\process\RedisQueueConsumer;
use app\queue\redis\CsvExportConsumer;

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
];
