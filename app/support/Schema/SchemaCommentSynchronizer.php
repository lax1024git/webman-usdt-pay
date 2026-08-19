<?php

declare(strict_types=1);

namespace app\support\Schema;

use Illuminate\Database\Capsule\Manager as DB;

class SchemaCommentSynchronizer
{
    /** @var array<string, array{table: string, columns: array<string, object>}> */
    private static array $metaCache = [];

    public static function sync(array $schema): bool
    {
        $table = $schema['table'];
        if (!DB::schema()->hasTable($table)) {
            return false;
        }

        $meta = self::loadMeta($table);
        $changed = false;

        if (!empty($schema['comment']) && $meta['table'] !== $schema['comment']) {
            DB::statement(
                'ALTER TABLE `' . self::escapeIdentifier($table) . '` COMMENT = '
                . DB::connection()->getPdo()->quote($schema['comment'])
            );
            $changed = true;
            self::$metaCache[$table]['table'] = (string) $schema['comment'];
        }

        foreach (self::collectColumnComments($schema) as $column => $comment) {
            $columnMeta = $meta['columns'][$column] ?? null;
            if ($columnMeta === null) {
                continue;
            }
            if ((string) ($columnMeta->COLUMN_COMMENT ?? '') === $comment) {
                continue;
            }
            self::modifyColumnComment($table, $column, $comment, $columnMeta);
            $changed = true;
            self::$metaCache[$table]['columns'][$column]->COLUMN_COMMENT = $comment;
        }

        return $changed;
    }

    public static function commentsNeedUpdate(array $schema): bool
    {
        $table = $schema['table'];
        if (!DB::schema()->hasTable($table)) {
            return false;
        }

        $meta = self::loadMeta($table);

        if (!empty($schema['comment']) && $meta['table'] !== $schema['comment']) {
            return true;
        }

        foreach (self::collectColumnComments($schema) as $column => $comment) {
            $columnMeta = $meta['columns'][$column] ?? null;
            if ($columnMeta === null) {
                continue;
            }
            if ((string) ($columnMeta->COLUMN_COMMENT ?? '') !== $comment) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    public static function collectColumnComments(array $schema): array
    {
        $comments = [];

        foreach ($schema['columns'] ?? [] as $name => $definition) {
            if (!empty($definition['comment'])) {
                $comments[$name] = (string) $definition['comment'];
            }
        }

        foreach ($schema['columnComments'] ?? [] as $name => $comment) {
            $comments[$name] = (string) $comment;
        }

        return $comments;
    }

    public static function clearCache(?string $table = null): void
    {
        if ($table === null) {
            self::$metaCache = [];
            return;
        }
        unset(self::$metaCache[$table]);
    }

    /**
     * @return array{table: string, columns: array<string, object>}
     */
    private static function loadMeta(string $table): array
    {
        if (isset(self::$metaCache[$table])) {
            return self::$metaCache[$table];
        }

        $tableRow = DB::selectOne(
            'SELECT TABLE_COMMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        $columnRows = DB::select(
            'SELECT COLUMN_NAME, COLUMN_COMMENT, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        $columns = [];
        foreach ($columnRows as $row) {
            $columns[(string) $row->COLUMN_NAME] = $row;
        }

        return self::$metaCache[$table] = [
            'table' => (string) ($tableRow->TABLE_COMMENT ?? ''),
            'columns' => $columns,
        ];
    }

    private static function modifyColumnComment(string $table, string $column, string $comment, object $row): void
    {
        $nullable = $row->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        $default = self::buildDefaultClause($row->COLUMN_DEFAULT, (string) $row->COLUMN_TYPE);
        $extra = self::normalizeExtra((string) $row->EXTRA, $default !== '');

        $sql = sprintf(
            'ALTER TABLE `%s` MODIFY COLUMN `%s` %s %s%s%s COMMENT %s',
            self::escapeIdentifier($table),
            self::escapeIdentifier($column),
            $row->COLUMN_TYPE,
            $nullable,
            $default,
            $extra !== '' ? ' ' . $extra : '',
            DB::connection()->getPdo()->quote($comment)
        );

        DB::statement($sql);
    }

    private static function normalizeExtra(string $extra, bool $hasDefaultClause): string
    {
        if ($extra === '') {
            return '';
        }

        $parts = preg_split('/\s+/', trim($extra)) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === 'DEFAULT_GENERATED' && $hasDefaultClause) {
                continue;
            }
            $normalized[] = $part;
        }

        return implode(' ', $normalized);
    }

    private static function buildDefaultClause(mixed $default, string $columnType): string
    {
        if ($default === null) {
            return '';
        }

        if (strtoupper((string) $default) === 'NULL') {
            return ' DEFAULT NULL';
        }

        if (strtoupper((string) $default) === 'CURRENT_TIMESTAMP') {
            return ' DEFAULT CURRENT_TIMESTAMP';
        }

        if (is_numeric($default) && !str_contains(strtolower($columnType), 'char') && !str_contains(strtolower($columnType), 'text')) {
            return ' DEFAULT ' . $default;
        }

        return ' DEFAULT ' . DB::connection()->getPdo()->quote((string) $default);
    }

    private static function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
