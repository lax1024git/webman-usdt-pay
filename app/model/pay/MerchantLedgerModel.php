<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class MerchantLedgerModel extends Model
{
    use DefinesTableSchema;

    public const BIZ_DEPOSIT = 'deposit';
    public const BIZ_WITHDRAW_FREEZE = 'withdraw_freeze';
    public const BIZ_WITHDRAW_UNFREEZE = 'withdraw_unfreeze';
    public const BIZ_WITHDRAW_SUCCESS = 'withdraw_success';
    public const BIZ_FEE = 'fee';

    protected $table = 'pa_merchant_ledger';

    public $timestamps = false;

    protected $fillable = [
        'merchant_id', 'account_id', 'biz_type', 'biz_id', 'order_no',
        'change_amount', 'available_after', 'frozen_after', 'remark', 'created_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'account_id' => 'integer',
        'biz_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_merchant_ledger',
            'comment' => '商户资金流水',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'merchant_id' => ['type' => 'unsignedInteger', 'comment' => '商户ID'],
                'account_id' => ['type' => 'unsignedInteger', 'comment' => '账户ID'],
                'biz_type' => ['type' => 'string', 'length' => 30, 'comment' => '业务类型'],
                'biz_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '业务ID'],
                'order_no' => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '订单号'],
                'change_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '变动金额'],
                'available_after' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '变动后可用'],
                'frozen_after' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '变动后冻结'],
                'remark' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '备注'],
                'created_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '创建时间'],
            ],
            'timestamps' => false,
            'indexes' => [
                ['columns' => ['merchant_id', 'created_at'], 'name' => 'idx_ledger_merchant_created'],
                ['columns' => ['order_no'], 'name' => 'idx_ledger_order_no'],
            ],
        ];
    }
}
