<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class WebhookLogModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_webhook_logs';

    public $timestamps = false;

    protected $fillable = [
        'merchant_id', 'order_no', 'event', 'request_url', 'request_body',
        'response_code', 'response_body', 'status', 'created_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'request_body' => 'array',
        'response_code' => 'integer',
        'created_at' => 'datetime',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'pa_webhook_logs',
            'comment' => 'Webhook 回调日志',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键'],
                'merchant_id' => ['type' => 'unsignedInteger', 'comment' => '商户ID'],
                'order_no' => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '订单号'],
                'event' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '事件'],
                'request_url' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => 'URL'],
                'request_body' => ['type' => 'json', 'nullable' => true, 'comment' => '请求体'],
                'response_code' => ['type' => 'integer', 'default' => 0, 'comment' => 'HTTP状态'],
                'response_body' => ['type' => 'text', 'nullable' => true, 'comment' => '响应体'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => 'success/failed'],
                'created_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '创建时间'],
            ],
            'timestamps' => false,
            'indexes' => [
                ['columns' => ['order_no'], 'name' => 'idx_webhook_order_no'],
            ],
        ];
    }
}
