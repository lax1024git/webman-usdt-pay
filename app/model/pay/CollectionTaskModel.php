<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class CollectionTaskModel extends Model
{
    use DefinesTableSchema;

    public const STATUS_PENDING = 'pending';
    public const STATUS_BROADCASTING = 'broadcasting';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'pa_collection_tasks';

    protected $fillable = [
        'platform_id', 'wallet_address_id', 'from_address', 'to_address',
        'amount', 'tx_hash', 'status', 'error_message',
    ];

    protected $casts = [
        'platform_id' => 'integer',
        'wallet_address_id' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_collection_tasks',
            'comment' => '归集任务',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'platform_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '通道ID'],
                'wallet_address_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '地址池ID'],
                'from_address' => ['type' => 'string', 'length' => 128, 'comment' => '来源地址'],
                'to_address' => ['type' => 'string', 'length' => 128, 'comment' => '目标热钱包'],
                'amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '金额'],
                'tx_hash' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => 'TxHash'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => '状态'],
                'error_message' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '失败原因'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status'], 'name' => 'idx_collection_status'],
                ['columns' => ['from_address'], 'name' => 'idx_collection_from'],
            ],
        ];
    }
}
