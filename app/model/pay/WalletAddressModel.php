<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class WalletAddressModel extends Model
{
    use DefinesTableSchema;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_HOT = 'hot';
    public const TYPE_COLD = 'cold';

    public const STATUS_DISABLED = 0;
    public const STATUS_AVAILABLE = 1;
    public const STATUS_ASSIGNED = 2;
    public const STATUS_COLLECTED = 3;

    protected $table = 'pa_wallet_addresses';

    protected $hidden = ['encrypted_key'];

    protected $fillable = [
        'platform_id', 'address', 'type', 'derivation_index', 'order_id',
        'encrypted_key', 'balance', 'balance_synced_at', 'status',
    ];

    protected $casts = [
        'platform_id' => 'integer',
        'derivation_index' => 'integer',
        'order_id' => 'integer',
        'status' => 'integer',
        'balance_synced_at' => 'datetime',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_wallet_addresses',
            'comment' => '钱包地址池',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键'],
                'platform_id' => ['type' => 'unsignedInteger', 'comment' => '通道ID'],
                'address' => ['type' => 'string', 'length' => 128, 'comment' => '地址'],
                'type' => ['type' => 'string', 'length' => 20, 'default' => 'deposit', 'comment' => 'deposit/hot/cold'],
                'derivation_index' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '派生索引'],
                'order_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '绑定订单'],
                'encrypted_key' => ['type' => 'text', 'nullable' => true, 'comment' => '加密私钥（HD派生）'],
                'balance' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '余额缓存'],
                'balance_synced_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '余额同步时间'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '状态'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['address'], 'unique' => true, 'name' => 'uk_wallet_address'],
                ['columns' => ['type', 'status'], 'name' => 'idx_wallet_type_status'],
            ],
        ];
    }
}
