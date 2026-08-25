<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class PlatformModel extends Model
{
    use DefinesTableSchema;

    public const AMOUNT_MATCH_EXACT = 'exact';
    public const AMOUNT_MATCH_TOLERANT = 'tolerant';
    public const AMOUNT_MATCH_ACTUAL = 'actual';

    protected $table = 'pa_platforms';

    protected $fillable = [
        'code', 'name', 'chain', 'currency', 'contract_address', 'decimals',
        'min_deposit_amount', 'max_deposit_amount', 'min_withdraw_amount', 'max_withdraw_amount',
        'deposit_confirmations', 'withdraw_confirmations', 'deposit_expire_seconds',
        'amount_match_mode', 'status', 'config', 'sort',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'deposit_confirmations' => 'integer',
        'withdraw_confirmations' => 'integer',
        'deposit_expire_seconds' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'config' => 'array',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_platforms',
            'comment' => 'USDT 支付通道',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键'],
                'code' => ['type' => 'string', 'length' => 30, 'comment' => '通道编码'],
                'name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '展示名'],
                'chain' => ['type' => 'string', 'length' => 20, 'default' => 'TRC20', 'comment' => '链类型'],
                'currency' => ['type' => 'string', 'length' => 20, 'default' => 'USDT', 'comment' => '币种'],
                'contract_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '代币合约'],
                'decimals' => ['type' => 'unsignedTinyInteger', 'default' => 6, 'comment' => '精度'],
                'min_deposit_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最小入金'],
                'max_deposit_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最大入金'],
                'min_withdraw_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最小出金'],
                'max_withdraw_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '最大出金'],
                'deposit_confirmations' => ['type' => 'unsignedInteger', 'default' => 19, 'comment' => '入金确认数'],
                'withdraw_confirmations' => ['type' => 'unsignedInteger', 'default' => 19, 'comment' => '出金确认数'],
                'deposit_expire_seconds' => ['type' => 'unsignedInteger', 'default' => 1800, 'comment' => '入金超时秒数'],
                'amount_match_mode' => ['type' => 'string', 'length' => 20, 'default' => 'exact', 'comment' => 'exact/tolerant/actual'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '0关1开'],
                'config' => ['type' => 'json', 'nullable' => true, 'comment' => '扩展配置'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['code'], 'unique' => true, 'name' => 'uk_platform_code'],
            ],
        ];
    }
}
