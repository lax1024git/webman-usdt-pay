<?php

declare(strict_types=1);

namespace app\service\pay;

use app\model\pay\DepositOrderModel;
use app\model\pay\MerchantModel;
use app\model\pay\WebhookLogModel;
use app\model\pay\WithdrawOrderModel;
use app\queue\redis\WebhookRetryConsumer;
use Webman\RedisQueue\Redis;

class WebhookService
{
    /** 指数退避秒数：1m / 5m / 15m / 30m / 1h */
    private const RETRY_DELAYS = [60, 300, 900, 1800, 3600];

    public function dispatchDeposit(DepositOrderModel $order, string $event): void
    {
        $merchant = MerchantModel::find($order->merchant_id);
        if (!$merchant) {
            return;
        }

        $payload = [
            'event' => $event,
            'order_no' => $order->order_no,
            'out_trade_no' => $order->out_trade_no,
            'chain' => $order->chain,
            'amount' => $order->amount,
            'paid_amount' => $order->paid_amount,
            'status' => $order->status,
            'tx_hash' => $order->tx_hash,
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
            'succeeded_at' => $order->succeeded_at?->format('Y-m-d H:i:s'),
        ];

        $this->enqueue($merchant, (string) ($order->notify_url ?: $merchant->notify_url), $payload);
    }

    public function dispatchWithdraw(WithdrawOrderModel $order, string $event): void
    {
        $merchant = MerchantModel::find($order->merchant_id);
        if (!$merchant) {
            return;
        }

        $payload = [
            'event' => $event,
            'order_no' => $order->order_no,
            'out_trade_no' => $order->out_trade_no,
            'chain' => $order->chain,
            'withdraw_amount' => $order->withdraw_amount,
            'payout_amount' => $order->payout_amount,
            'to_address' => $order->to_address,
            'status' => $order->status,
            'tx_hash' => $order->tx_hash,
            'reject_reason' => $order->reject_reason,
        ];

        $this->enqueue($merchant, (string) ($order->notify_url ?: $merchant->notify_url), $payload);
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = WebhookLogModel::query();
        if (!empty($filters['order_no'])) {
            $query->where('order_no', $filters['order_no']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', (int) $filters['merchant_id']);
        }
        $total = $query->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function retry(int $logId, ?int $merchantId = null): void
    {
        $query = WebhookLogModel::query()->where('id', $logId);
        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }
        $log = $query->first();
        if (!$log) {
            return;
        }
        $payload = $log->request_body;
        if (!is_array($payload)) {
            return;
        }
        $this->sendQueue((int) $log->merchant_id, (string) $log->request_url, $payload, 0, 0);
    }

    /**
     * 补偿扫描：对仍失败且无后续成功的回调重新入队。
     */
    public function compensateFailed(int $limit = 50): int
    {
        $count = 0;
        $logs = WebhookLogModel::query()
            ->where('status', 'failed')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $hasSuccess = WebhookLogModel::query()
                ->where('merchant_id', $log->merchant_id)
                ->where('order_no', $log->order_no)
                ->where('event', $log->event)
                ->where('status', 'success')
                ->where('id', '>', $log->id)
                ->exists();
            if ($hasSuccess) {
                continue;
            }

            $payload = $log->request_body;
            if (!is_array($payload) || $log->request_url === '') {
                continue;
            }

            $this->sendQueue((int) $log->merchant_id, (string) $log->request_url, $payload, 0, 0);
            $count++;
        }

        return $count;
    }

    public function deliver(int $merchantId, string $url, array $payload, string $plainSecret, int $attempt = 0): bool
    {
        if ($url === '') {
            return false;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . "\n" . $body, $plainSecret);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Pay-Timestamp: ' . $timestamp,
                'X-Pay-Signature: ' . $signature,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = $code >= 200 && $code < 300;
        WebhookLogModel::create([
            'merchant_id' => $merchantId,
            'order_no' => (string) ($payload['order_no'] ?? ''),
            'event' => (string) ($payload['event'] ?? ''),
            'request_url' => $url,
            'request_body' => $payload,
            'response_code' => $code,
            'response_body' => is_string($response) ? substr($response, 0, 2000) : '',
            'status' => $ok ? 'success' : 'failed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->syncOrderNotify(
            (string) ($payload['order_no'] ?? ''),
            (string) ($payload['event'] ?? ''),
            $ok
        );

        if (!$ok) {
            $this->scheduleRetry($merchantId, $url, $payload, $attempt);
        }

        return $ok;
    }

    private function enqueue(MerchantModel $merchant, string $url, array $payload): void
    {
        if ($url === '') {
            return;
        }
        $this->sendQueue((int) $merchant->id, $url, $payload, 0, 0);
    }

    private function scheduleRetry(int $merchantId, string $url, array $payload, int $attempt): void
    {
        $maxRetry = max(0, (int) env('PAY_WEBHOOK_MAX_RETRY', 5));
        $nextAttempt = $attempt + 1;
        if ($nextAttempt > $maxRetry) {
            return;
        }

        $delayIndex = min($attempt, count(self::RETRY_DELAYS) - 1);
        $delay = self::RETRY_DELAYS[$delayIndex];
        $this->sendQueue($merchantId, $url, $payload, $nextAttempt, $delay);
    }

    private function sendQueue(int $merchantId, string $url, array $payload, int $attempt, int $delay): void
    {
        $data = [
            'merchant_id' => $merchantId,
            'url' => $url,
            'payload' => $payload,
            'attempt' => $attempt,
        ];
        if ($delay > 0) {
            Redis::send(WebhookRetryConsumer::QUEUE_NAME, $data, $delay);
        } else {
            Redis::send(WebhookRetryConsumer::QUEUE_NAME, $data);
        }
    }

    private function syncOrderNotify(string $orderNo, string $event, bool $ok): void
    {
        if ($orderNo === '') {
            return;
        }

        $status = $ok ? 'success' : 'failed';
        if (str_starts_with($event, 'deposit.')) {
            $order = DepositOrderModel::where('order_no', $orderNo)->first();
            if ($order) {
                $order->update([
                    'notify_status' => $status,
                    'notify_times' => (int) $order->notify_times + 1,
                ]);
            }
            return;
        }

        if (str_starts_with($event, 'withdraw.')) {
            $order = WithdrawOrderModel::where('order_no', $orderNo)->first();
            if ($order) {
                $order->update([
                    'notify_status' => $status,
                    'notify_times' => (int) $order->notify_times + 1,
                ]);
            }
        }
    }
}
