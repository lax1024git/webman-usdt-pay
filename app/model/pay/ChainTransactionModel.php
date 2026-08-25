<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ChainTransactionModel extends Model
{
    use DefinesTableSchema;

    public const BIZ_DEPOSIT = 'deposit';
    public const BIZ_WITHDRAW = 'withdraw';
    public const BIZ_COLLECTION = 'collection';

    protected $table = 'pa_chain_transactions';

    protected $fillable = [
        'chain', 'tx_hash', 'log_index', 'block_number', 'from_address', 'to_address',
        'amount', 'token_contract', 'biz_type', 'biz_id', 'confirmations', 'status', 'raw',
    ];

    protected $casts = [
        'log_index' => 'integer',
        'block_number' => 'integer',
        'biz_id' => 'integer',
        'confirmations' => 'integer',
        'raw' => 'array',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_chain_transactions',
            'comment' => '链上交易记录',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'chain' => ['type' => 'string', 'length' => 20, 'comment' => '链'],
                'tx_hash' => ['type' => 'string', 'length' => 128, 'comment' => '哈希'],
                'log_index' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '事件索引'],
                'block_number' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '区块号'],
                'from_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => 'from'],
                'to_address' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => 'to'],
                'amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => '0', 'comment' => '金额'],
                'token_contract' => ['type' => 'string', 'length' => 128, 'default' => '', 'comment' => '合约'],
                'biz_type' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '业务类型'],
                'biz_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '业务ID'],
                'confirmations' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '确认数'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'detected', 'comment' => '状态'],
                'raw' => ['type' => 'json', 'nullable' => true, 'comment' => '原始数据'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['chain', 'tx_hash', 'log_index'], 'unique' => true, 'name' => 'uk_chain_tx_log'],
            ],
        ];
    }
}
