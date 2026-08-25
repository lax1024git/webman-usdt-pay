<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawOrderModel extends Model
{
    use DefinesTableSchema;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAYING = 'paying';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'pa_withdraw_orders';

    protected $fillable = [
        'order_no', 'out_trade_no', 'merchant_id', 'platform_id', 'chain', 'currency',
        'withdraw_amount', 'fee_amount', 'payout_amount', 'to_address', 'status', 'tx_hash',
        'confirmations', 'reviewer_id', 'reviewed_at', 'reject_reason', 'notify_url',
        'notify_status', 'notify_times', 'paid_at', 'succeeded_at', 'extra', 'remark',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'platform_id' => 'integer',
        'confirmations' => 'integer',
        'reviewer_id' => 'integer',
        'notify_times' => 'integer',
        'extra' => 'array',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantModel::class, 'merchant_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(PlatformModel::class, 'platform_id');
    }

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_withdraw_orders',
            'comment' => 'USDT 出金订单',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'order_no' => ['type' => 'string', 'length' => 64, 'comment' => '平台订单号'],
                'out_trade_no' => ['type' => 'string', 'length' => 128, 'comment' => '商户订单号'],
                'merchant_id' => ['type' => 'unsignedInteger', 'comment' => '商户ID'],
                'platform_id' => ['type' => 'unsignedInteger', 'comment' => '通道ID'],
                'chain' => ['type' => 'string', 'length' => 20, 'default' => 'TRC20', 'comment' => '链'],
                'currency' => ['type' => 'string', 'length' => 20, 'default' => 'USDT', 'comment' => '币种'],
                'withdraw_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '申请金额'],
                'fee_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '手续费'],
                'payout_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '链上实付'],
                'to_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '目标地址'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => '状态'],
                'tx_hash' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '交易哈希'],
                'confirmations' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '确认数'],
                'reviewer_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '审核人'],
                'reviewed_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '审核时间'],
                'reject_reason' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '驳回原因'],
                'notify_url' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '回调地址'],
                'notify_status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => '回调状态'],
                'notify_times' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '回调次数'],
                'paid_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '广播时间'],
                'succeeded_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '完成时间'],
                'extra' => ['type' => 'json', 'nullable' => true, 'comment' => '扩展'],
                'remark' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['order_no'], 'unique' => true, 'name' => 'uk_withdraw_order_no'],
                ['columns' => ['merchant_id', 'out_trade_no'], 'unique' => true, 'name' => 'uk_withdraw_merchant_out'],
                ['columns' => ['status', 'created_at'], 'name' => 'idx_withdraw_status_created'],
                ['columns' => ['tx_hash'], 'name' => 'idx_withdraw_tx_hash'],
            ],
        ];
    }
}
