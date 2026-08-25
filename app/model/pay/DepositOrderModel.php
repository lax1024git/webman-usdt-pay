<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositOrderModel extends Model
{
    use DefinesTableSchema;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DETECTING = 'detecting';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MANUAL = 'manual';

    protected $table = 'pa_deposit_orders';

    protected $fillable = [
        'order_no', 'out_trade_no', 'merchant_id', 'platform_id', 'chain', 'currency',
        'amount', 'paid_amount', 'fee_amount', 'net_amount', 'deposit_address', 'wallet_address_id',
        'status', 'tx_hash', 'from_address', 'confirmations', 'notify_url', 'notify_status',
        'notify_times', 'expired_at', 'paid_at', 'succeeded_at', 'extra', 'remark',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'platform_id' => 'integer',
        'wallet_address_id' => 'integer',
        'confirmations' => 'integer',
        'notify_times' => 'integer',
        'extra' => 'array',
        'expired_at' => 'datetime',
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
            'table' => 'pa_deposit_orders',
            'comment' => 'USDT 入金订单',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'order_no' => ['type' => 'string', 'length' => 64, 'comment' => '平台订单号'],
                'out_trade_no' => ['type' => 'string', 'length' => 128, 'comment' => '商户订单号'],
                'merchant_id' => ['type' => 'unsignedInteger', 'comment' => '商户ID'],
                'platform_id' => ['type' => 'unsignedInteger', 'comment' => '通道ID'],
                'chain' => ['type' => 'string', 'length' => 20, 'default' => 'TRC20', 'comment' => '链'],
                'currency' => ['type' => 'string', 'length' => 20, 'default' => 'USDT', 'comment' => '币种'],
                'amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '应付金额'],
                'paid_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '实付金额'],
                'fee_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '手续费'],
                'net_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '入账金额'],
                'deposit_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '收款地址'],
                'wallet_address_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '地址池ID'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => '状态'],
                'tx_hash' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '交易哈希'],
                'from_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '付款地址'],
                'confirmations' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '确认数'],
                'notify_url' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '回调地址'],
                'notify_status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => '回调状态'],
                'notify_times' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '回调次数'],
                'expired_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '过期时间'],
                'paid_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '链上到账时间'],
                'succeeded_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '入账完成时间'],
                'extra' => ['type' => 'json', 'nullable' => true, 'comment' => '扩展'],
                'remark' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['order_no'], 'unique' => true, 'name' => 'uk_deposit_order_no'],
                ['columns' => ['merchant_id', 'out_trade_no'], 'unique' => true, 'name' => 'uk_deposit_merchant_out'],
                ['columns' => ['status', 'created_at'], 'name' => 'idx_deposit_status_created'],
                ['columns' => ['deposit_address'], 'name' => 'idx_deposit_address'],
                ['columns' => ['tx_hash'], 'name' => 'idx_deposit_tx_hash'],
            ],
        ];
    }
}
