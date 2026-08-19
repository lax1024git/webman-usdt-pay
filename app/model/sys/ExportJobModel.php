<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ExportJobModel extends Model
{
    use DefinesTableSchema;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'sy_export_jobs';

    protected $fillable = [
        'export_type', 'filters', 'operator_id',
        'status', 'total', 'processed', 'batch_size', 'cursor_id',
        'file_path', 'file_key', 'file_url',
        'message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'operator_id' => 'integer',
        'total' => 'integer',
        'processed' => 'integer',
        'batch_size' => 'integer',
        'cursor_id' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_export_jobs',
            'comment' => '统一 CSV 导出异步任务',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'export_type' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '导出类型 members/member_water/withdraw_orders'],
                'filters' => ['type' => 'json', 'nullable' => true, 'comment' => '导出筛选条件JSON'],
                'operator_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '操作管理员ID'],
                'status' => ['type' => 'string', 'length' => 20, 'default' => 'pending', 'comment' => 'pending/running/success/failed'],
                'total' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '预计总行数'],
                'processed' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '已写入行数'],
                'batch_size' => ['type' => 'unsignedInteger', 'default' => 500, 'comment' => '每批查询数量'],
                'cursor_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '游标：已处理的最小ID'],
                'file_path' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '本地临时文件路径'],
                'file_key' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => 'S3/本地对象Key'],
                'file_url' => ['type' => 'string', 'length' => 1000, 'default' => '', 'comment' => '下载地址'],
                'message' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '状态说明/错误信息'],
                'started_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '开始时间'],
                'finished_at' => ['type' => 'dateTime', 'nullable' => true, 'comment' => '结束时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['export_type', 'status'], 'name' => 'idx_export_type_status'],
                ['columns' => ['operator_id', 'status'], 'name' => 'idx_export_operator_status'],
                ['columns' => ['status'], 'name' => 'idx_export_status'],
            ],
        ];
    }
}
