<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\service\ExportJobService;
use Webman\RedisQueue\Consumer;

class CsvExportConsumer implements Consumer
{
    public const QUEUE_NAME = 'export';

    public $queue = self::QUEUE_NAME;

    public $connection = 'default';

    public function consume($data): void
    {
        $jobId = (int) ($data['job_id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        (new ExportJobService())->processBatch($jobId);
    }

    public function onConsumeFailure(\Throwable $e, $package): void
    {
        // processBatch 内已落 failed 状态
    }
}
