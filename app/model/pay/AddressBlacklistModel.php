<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AddressBlacklistModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_address_blacklist';

    public $timestamps = false;

    protected $fillable = ['chain', 'address', 'reason', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_address_blacklist',
            'comment' => '出金地址黑名单',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键'],
                'chain' => ['type' => 'string', 'length' => 20, 'comment' => '链'],
                'address' => ['type' => 'string', 'length' => 128, 'comment' => '地址'],
                'reason' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '原因'],
                'created_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '创建时间'],
            ],
            'timestamps' => false,
            'indexes' => [
                ['columns' => ['chain', 'address'], 'unique' => true, 'name' => 'uk_blacklist_chain_address'],
            ],
        ];
    }
}
