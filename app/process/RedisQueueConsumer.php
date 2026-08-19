<?php

declare(strict_types=1);

namespace app\process;

use support\Container;
use support\Context;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Consumer;

/**
 * 扫描 consumer_dir，仅订阅指定队列（同目录多 Consumer 时按队列隔离进程）。
 */
class RedisQueueConsumer
{
    protected string $consumerDir;

    /** @var list<string> */
    protected array $queues;

    /** @var array<string, Consumer> */
    protected array $consumers = [];

    /**
     * @param list<string> $queues
     */
    public function __construct(string $consumer_dir = '', array $queues = [])
    {
        $this->consumerDir = $consumer_dir;
        $this->queues = array_values(array_filter(array_map('strval', $queues)));
    }

    public function onWorkerStart(): void
    {
        if ($this->consumerDir === '' || !is_dir($this->consumerDir)) {
            echo "Consumer directory {$this->consumerDir} not exists\r\n";

            return;
        }

        if ($this->queues === []) {
            echo "RedisQueueConsumer queues is empty, skip subscribe\r\n";

            return;
        }

        $allowed = array_fill_keys($this->queues, true);
        $dirIterator = new \RecursiveDirectoryIterator($this->consumerDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $pathname = $file->getPathname();
            $class = str_replace('/', '\\', substr(substr($pathname, strlen(base_path())), 0, -4));
            if (!is_a($class, Consumer::class, true)) {
                continue;
            }

            /** @var Consumer $consumer */
            $consumer = Container::get($class);
            $queue = (string) ($consumer->queue ?? '');
            if ($queue === '' || !isset($allowed[$queue])) {
                continue;
            }

            $this->consumers[$queue] = $consumer;
            $connectionName = $consumer->connection ?? 'default';
            $connection = Client::connection($connectionName);
            $consumerFunc = static function ($message) use ($consumer): void {
                try {
                    $consumer->consume($message);
                } finally {
                    Context::destroy();
                }
            };
            $connection->subscribe($queue, $consumerFunc);

            if (method_exists($connection, 'onConsumeFailure')) {
                $connection->onConsumeFailure(function ($exception, $package) {
                    $queueName = (string) ($package['queue'] ?? '');
                    $consumer = $this->consumers[$queueName] ?? null;
                    if ($consumer !== null && method_exists($consumer, 'onConsumeFailure')) {
                        return call_user_func([$consumer, 'onConsumeFailure'], $exception, $package);
                    }

                    return null;
                });
            }
        }
    }
}
