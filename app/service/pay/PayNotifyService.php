<?php

declare(strict_types=1);

namespace app\service\pay;

use app\model\pay\DepositOrderModel;
use app\model\pay\WithdrawOrderModel;
use app\service\NotificationService;

class PayNotifyService
{
    public function __construct(
        protected ?NotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    public function depositReviewing(DepositOrderModel $order): void
    {
        $this->notify(
            '入金待确认',
            "订单 {$order->order_no} 检测到链上转账 {$order->paid_amount} USDT",
            'recharge',
            (int) $order->id,
            '/pay/deposit'
        );
    }

    public function depositSuccess(DepositOrderModel $order): void
    {
        $this->notify(
            '入金成功',
            "订单 {$order->order_no} 入账 {$order->net_amount} USDT",
            'recharge',
            (int) $order->id,
            '/pay/deposit'
        );
    }

    public function withdrawReviewing(WithdrawOrderModel $order): void
    {
        $this->notify(
            '出金待审核',
            "订单 {$order->order_no} 申请 {$order->withdraw_amount} USDT → {$order->to_address}",
            'withdraw',
            (int) $order->id,
            '/pay/withdraw'
        );
    }

    public function withdrawSuccess(WithdrawOrderModel $order): void
    {
        $this->notify(
            '出金成功',
            "订单 {$order->order_no} 已出款 {$order->payout_amount} USDT",
            'withdraw',
            (int) $order->id,
            '/pay/withdraw'
        );
    }

    /**
     * 热钱包告警（同 key 30 分钟内不重复）。
     */
    public function hotWalletAlert(string $key, string $title, string $content): void
    {
        $redis = \support\Redis::connection();
        $cacheKey = 'pay_hot_alert:' . $key;
        if ($redis->get($cacheKey)) {
            return;
        }
        $redis->setex($cacheKey, 1800, '1');

        $this->notify($title, $content, 'hot_wallet', 0, '/pay/wallet');
    }

    private function notify(string $title, string $content, string $bizType, int $bizId, string $link): void
    {
        $this->notificationService->create([
            'admin_id' => 0,
            'title' => $title,
            'content' => $content,
            'type' => 'notice',
            'biz_type' => $bizType,
            'biz_id' => $bizId,
            'link' => $link,
            'is_read' => false,
        ]);
    }
}
