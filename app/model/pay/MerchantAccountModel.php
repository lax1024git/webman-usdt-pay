<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAccountModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_merchant_accounts';

    public $timestamps = false;

    protected $fillable = [
        'merchant_id', 'currency', 'chain', 'available', 'frozen', 'version', 'updated_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'version' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantModel::class, 'merchant_id');
    }

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_merchant_accounts',
            'comment' => '商户余额账户',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键'],
                'merchant_id' => ['type' => 'unsignedInteger', 'comment' => '商户ID'],
                'currency' => ['type' => 'string', 'length' => 20, 'default' => 'USDT', 'comment' => '币种'],
                'chain' => ['type' => 'string', 'length' => 20, 'default' => 'TRC20', 'comment' => '链'],
                'available' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '可用余额'],
                'frozen' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '冻结余额'],
                'version' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '乐观锁'],
                'updated_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '更新时间'],
            ],
            'timestamps' => false,
            'indexes' => [
                ['columns' => ['merchant_id', 'currency', 'chain'], 'unique' => true, 'name' => 'uk_merchant_currency_chain'],
            ],
        ];
    }
}
