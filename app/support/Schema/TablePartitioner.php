<?php

declare(strict_types=1);

namespace app\support\Schema;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;
use Throwable;

/**
 * 按模型 tableSchema['partition'] 应用 / 扩展 MySQL 按月 RANGE 分区。
 *
 * DATETIME/TIMESTAMP → PARTITION BY RANGE COLUMNS(`col`)
 * unix 整型列     → PARTITION BY RANGE (`col`)
 *
 * 开发阶段可用 force：REMOVE PARTITIONING 后重建；可选 truncate。
 */
final class TablePartitioner
{
    /**
     * @param array<string, mixed> $schema
     * @param array{
     *   force?: bool,
     *   truncate?: bool,
     *   monthsAhead?: int|null,
     *   monthsBack?: int|null,
     *   extendOnly?: bool
     * } $options
     * @return array{status: string, table?: string, reason?: string, sql?: string, action?: string}
     */
    public static function apply(array $schema, array $options = []): array
    {
        $partition = $schema['partition'] ?? null;
        if (!is_array($partition) || empty($partition['enabled'])) {
            return ['status' => 'skipped', 'reason' => 'partition disabled'];
        }

        $table = (string) ($schema['table'] ?? '');
        $column = (string) ($partition['column'] ?? 'created_at');
        if ($table === '') {
            throw new RuntimeException('partition schema missing table name');
        }

        $force = !empty($options['force']);
        $truncate = !empty($options['truncate']);
        $extendOnly = !empty($options['extendOnly']);

        $monthsAhead = (int) ($options['monthsAhead']
            ?? $partition['monthsAhead']
            ?? 24);
        $monthsBack = array_key_exists('monthsBack', $options)
            ? $options['monthsBack']
            : ($partition['monthsBack'] ?? 12);
        $monthsBack = $monthsBack === null ? null : (int) $monthsBack;

        $primary = $partition['primary'] ?? ['id', $column];
        if (!in_array($column, $primary, true)) {
            $primary[] = $column;
        }

        $valueType = (string) ($partition['valueType'] ?? 'datetime');
        $useRangeColumns = in_array($valueType, ['timestamp', 'datetime'], true)
            || self::columnIsDateTime($table, $column);

        $already = self::isPartitioned($table);

        if ($already && $extendOnly) {
            return self::extend($table, $column, $useRangeColumns, $monthsAhead);
        }

        if ($already && !$force) {
            // 默认尝试扩展未来分区，避免每月手动处理
            $extended = self::extend($table, $column, $useRangeColumns, $monthsAhead);
            if ($extended['status'] === 'extended') {
                return $extended;
            }

            return ['status' => 'skipped', 'reason' => 'already partitioned', 'table' => $table];
        }

        if ($already && $force) {
            DB::statement("ALTER TABLE `{$table}` REMOVE PARTITIONING");
        }

        if ($truncate) {
            DB::statement("TRUNCATE TABLE `{$table}`");
        }

        self::ensureCreatedAtNotNull($table, $column, $useRangeColumns);
        self::ensurePrimaryKey($table, $primary);
        self::ensureUniqueKeysIncludePartitionColumn($table, $column);

        if ($monthsBack === null) {
            $monthsBack = self::detectMonthsBack($table, $column, $useRangeColumns);
        }

        $sql = self::buildPartitionSql($table, $column, $useRangeColumns, $monthsAhead, $monthsBack);
        DB::statement($sql);

        return [
            'status' => 'applied',
            'action' => $force ? 'force-rebuild' : 'create',
            'table' => $table,
            'sql' => $sql,
        ];
    }

    /**
     * 将 pmax 之前补齐到「当前月 + monthsAhead」。
     *
     * @return array{status: string, table?: string, reason?: string, sql?: string, action?: string}
     */
    public static function extend(
        string $table,
        string $column,
        bool $useRangeColumns,
        int $monthsAhead = 24
    ): array {
        if (!self::isPartitioned($table)) {
            return ['status' => 'skipped', 'reason' => 'not partitioned', 'table' => $table];
        }

        $existing = self::partitionBoundaries($table);
        if ($existing === []) {
            return ['status' => 'skipped', 'reason' => 'no partitions', 'table' => $table];
        }

        $timezone = self::appTimezone();
        $targetEnd = (new DateTimeImmutable('first day of this month midnight', $timezone))
            ->modify('+' . ($monthsAhead + 1) . ' months');

        // 找 pmax 之前最后一个有界分区
        $lastBound = null;
        foreach ($existing as $name => $bound) {
            if (strtoupper((string) $bound) === 'MAXVALUE') {
                continue;
            }
            $lastBound = $bound;
        }

        if ($lastBound === null) {
            return ['status' => 'skipped', 'reason' => 'only pmax exists', 'table' => $table];
        }

        $cursor = $useRangeColumns
            ? new DateTimeImmutable(trim((string) $lastBound, "'\""), $timezone)
            : (new DateTimeImmutable('@' . (int) $lastBound))->setTimezone($timezone);

        if ($cursor >= $targetEnd) {
            return ['status' => 'skipped', 'reason' => 'already covers monthsAhead', 'table' => $table];
        }

        $parts = [];
        while ($cursor < $targetEnd) {
            $name = 'p' . $cursor->format('Ym');
            $next = $cursor->modify('+1 month');
            $parts[] = $useRangeColumns
                ? sprintf("PARTITION %s VALUES LESS THAN ('%s')", $name, $next->format('Y-m-d H:i:s'))
                : sprintf('PARTITION %s VALUES LESS THAN (%d)', $name, $next->getTimestamp());
            $cursor = $next;
        }
        $parts[] = 'PARTITION pmax VALUES LESS THAN MAXVALUE';

        $sql = sprintf(
            'ALTER TABLE `%s` REORGANIZE PARTITION pmax INTO (%s)',
            $table,
            implode(', ', $parts)
        );
        DB::statement($sql);

        return [
            'status' => 'extended',
            'action' => 'reorganize-pmax',
            'table' => $table,
            'sql' => $sql,
        ];
    }

    public static function isPartitioned(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT CREATE_OPTIONS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $row !== null && str_contains((string) ($row->CREATE_OPTIONS ?? ''), 'partitioned');
    }

    /**
     * @return array<string, string|null> partition_name => description
     */
    public static function partitionBoundaries(string $table): array
    {
        $rows = DB::select(
            'SELECT PARTITION_NAME, PARTITION_DESCRIPTION
             FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL
             ORDER BY PARTITION_ORDINAL_POSITION',
            [$table]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->PARTITION_NAME] = $row->PARTITION_DESCRIPTION !== null
                ? (string) $row->PARTITION_DESCRIPTION
                : null;
        }

        return $out;
    }

    /**
     * @param list<string> $primary
     */
    private static function ensurePrimaryKey(string $table, array $primary): void
    {
        $current = self::currentPrimaryKeyColumns($table);
        if ($current === $primary) {
            return;
        }

        $cols = implode('`, `', $primary);
        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`{$cols}`)");
    }

    /**
     * MySQL：所有 UNIQUE 必须包含分区列；否则降为普通索引。
     */
    private static function ensureUniqueKeysIncludePartitionColumn(string $table, string $column): void
    {
        $rows = DB::select(
            "SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
             GROUP BY INDEX_NAME, NON_UNIQUE",
            [$table]
        );

        foreach ($rows as $row) {
            $indexName = (string) $row->INDEX_NAME;
            $cols = array_values(array_filter(array_map('trim', explode(',', (string) $row->cols))));
            if (in_array($column, $cols, true)) {
                continue;
            }

            $colList = implode('`, `', $cols);
            DB::statement(
                "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`, ADD INDEX `{$indexName}` (`{$colList}`)"
            );
        }
    }

    private static function ensureCreatedAtNotNull(string $table, string $column, bool $useRangeColumns): void
    {
        $row = DB::selectOne(
            "SELECT IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        if ($row === null) {
            throw new RuntimeException("column {$table}.{$column} not found");
        }

        if ((string) $row->IS_NULLABLE !== 'YES') {
            return;
        }

        // 空值先填当前时间 / 0，再改 NOT NULL
        if ($useRangeColumns) {
            $now = (new DateTimeImmutable('now', self::appTimezone()))->format('Y-m-d H:i:s');
            DB::statement("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` IS NULL", [$now]);
            $type = (string) $row->COLUMN_TYPE;
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$type} NOT NULL");
        } else {
            DB::statement("UPDATE `{$table}` SET `{$column}` = 0 WHERE `{$column}` IS NULL");
            $type = (string) $row->COLUMN_TYPE;
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$type} NOT NULL DEFAULT 0");
        }
    }

    /**
     * @return list<string>
     */
    private static function currentPrimaryKeyColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION",
            [$table]
        );

        return array_map(static fn ($r) => (string) $r->COLUMN_NAME, $rows);
    }

    private static function columnIsDateTime(string $table, string $column): bool
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        return in_array((string) ($row->DATA_TYPE ?? ''), ['timestamp', 'datetime', 'date'], true);
    }

    private static function detectMonthsBack(string $table, string $column, bool $useRangeColumns): int
    {
        try {
            $min = DB::selectOne("SELECT MIN(`{$column}`) AS v FROM `{$table}`");
            $raw = $min->v ?? null;
            if ($raw === null || $raw === '' || (int) $raw === 0) {
                return 3;
            }

            $timezone = self::appTimezone();
            $minDate = $useRangeColumns
                ? new DateTimeImmutable((string) $raw, $timezone)
                : (new DateTimeImmutable('@' . (int) $raw))->setTimezone($timezone);

            $thisMonth = new DateTimeImmutable('first day of this month midnight', $timezone);
            $minMonth = $minDate->modify('first day of this month midnight');
            $diff = (int) $minMonth->diff($thisMonth)->format('%y') * 12
                + (int) $minMonth->diff($thisMonth)->format('%m');

            return max(3, min(120, $diff + 1));
        } catch (Throwable) {
            return 12;
        }
    }

    private static function buildPartitionSql(
        string $table,
        string $column,
        bool $useRangeColumns,
        int $monthsAhead,
        int $monthsBack
    ): string {
        $timezone = self::appTimezone();
        $start = (new DateTimeImmutable('first day of this month midnight', $timezone))
            ->modify("-{$monthsBack} months");

        $cursor = $start;
        $total = $monthsBack + $monthsAhead + 1;
        $parts = [];

        for ($i = 0; $i < $total; $i++) {
            $name = 'p' . $cursor->format('Ym');
            $next = $cursor->modify('+1 month');

            $parts[] = $useRangeColumns
                ? sprintf("PARTITION %s VALUES LESS THAN ('%s')", $name, $next->format('Y-m-d H:i:s'))
                : sprintf('PARTITION %s VALUES LESS THAN (%d)', $name, $next->getTimestamp());

            $cursor = $next;
        }

        $parts[] = 'PARTITION pmax VALUES LESS THAN MAXVALUE';
        $definitions = implode(', ', $parts);

        if ($useRangeColumns) {
            return sprintf(
                'ALTER TABLE `%s` PARTITION BY RANGE COLUMNS(`%s`) (%s)',
                $table,
                $column,
                $definitions
            );
        }

        return sprintf(
            'ALTER TABLE `%s` PARTITION BY RANGE (`%s`) (%s)',
            $table,
            $column,
            $definitions
        );
    }

    private static function appTimezone(): DateTimeZone
    {
        $name = (string) (config('app.default_timezone') ?? date_default_timezone_get() ?: 'Asia/Shanghai');

        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone('Asia/Shanghai');
        }
    }
}
