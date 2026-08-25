<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\service\pay\MerchantService;
use app\service\pay\WebhookService;
use Webman\RedisQueue\Consumer;

class WebhookRetryConsumer implements Consumer
{
    public const QUEUE_NAME = 'pay_webhook';

    public $queue = self::QUEUE_NAME;

    public $connection = 'default';

    public function consume($data): void
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $url = (string) ($data['url'] ?? '');
        $payload = $data['payload'] ?? [];
        $attempt = (int) ($data['attempt'] ?? 0);
        if ($merchantId <= 0 || $url === '' || !is_array($payload)) {
            return;
        }

        $secret = (new MerchantService())->getPlainSecret($merchantId);
        if ($secret === '') {
            return;
        }

        (new WebhookService())->deliver($merchantId, $url, $payload, $secret, $attempt);
    }
}
