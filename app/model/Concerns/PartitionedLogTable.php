<?php

declare(strict_types=1);

namespace app\model\Concerns;

/**
 * 追加型日志表分区配置（按月 RANGE）。
 *
 * 约定（开发阶段统一）：
 * - 分区键默认 `created_at`（DATETIME）→ MySQL `PARTITION BY RANGE COLUMNS`
 * - 主键必须含分区列：`(id, created_at)`
 * - 不含分区列的 UNIQUE 会在应用分区时降为普通 INDEX
 *
 * valueType:
 * - datetime|timestamp → RANGE COLUMNS(col)
 * - unix → RANGE (col)（列本身为整型 Unix 秒）
 *
 * @return array<string, mixed>
 */
trait PartitionedLogTable
{
    /**
     * @param array{
     *   monthsAhead?: int,
     *   monthsBack?: int|null,
     *   primary?: list<string>
     * } $options
     * @return array<string, mixed>
     */
    protected static function monthlyPartition(
        string $column = 'created_at',
        string $valueType = 'datetime',
        array $options = []
    ): array {
        return [
            'enabled' => true,
            'column' => $column,
            'valueType' => $valueType,
            'granularity' => 'month',
            'monthsAhead' => (int) ($options['monthsAhead'] ?? 24),
            'monthsBack' => array_key_exists('monthsBack', $options)
                ? $options['monthsBack']
                : 12,
            'primary' => $options['primary'] ?? ['id', $column],
        ];
    }
}
