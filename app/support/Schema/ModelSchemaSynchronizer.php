<?php

declare(strict_types=1);

namespace app\support\Schema;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Capsule\Manager as DB;
use ReflectionClass;

class ModelSchemaSynchronizer
{
    private const SYNC_ORDER = [
        // sys 模块 (sy_)
        'AdminIpWhitelistModel' => 5,
        'AdminModel' => 10,
        'RoleModel' => 20,
        'PermissionModel' => 30,
        'DictTypeModel' => 35,
        'AdminNotificationModel' => 45,
        'SettingModel' => 60,
        'LangModel' => 62,
        'LangItemModel' => 63,
        'LangItemDetailModel' => 64,
        'AdminLogModel' => 70,
        'AdminRoleModel' => 80,
        'DictItemModel' => 85,
        'RolePermissionModel' => 90,
        'ExportJobModel' => 95,
    ];

    /**
     * @return array<int, array{class: class-string, schema: array}>
     */
    public function discover(?string $modelName = null): array
    {
        $models = [];

        $patterns = [
            ['glob' => base_path('app/model/*.php'), 'namespace' => 'app\\model'],
            ['glob' => base_path('app/model/sys/*.php'), 'namespace' => 'app\\model\\sys'],
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern['glob']) ?: [] as $file) {
                $baseName = basename($file, '.php');
                if ($modelName !== null) {
                    $target = self::normalizeModelClassName($modelName);
                    if (strcasecmp($baseName, $target) !== 0) {
                        continue;
                    }
                }

                $class = $pattern['namespace'] . '\\' . $baseName;
                if (!class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || !$reflection->hasMethod('tableSchema')) {
                    continue;
                }

                if (!in_array(DefinesTableSchema::class, class_uses($class), true)) {
                    continue;
                }

                $schema = $class::tableSchema();
                $schema['table'] = $schema['table'] ?? $this->guessTableName($baseName);

                $models[] = [
                    'class' => $class,
                    'name' => $baseName,
                    'schema' => $schema,
                    'order' => self::SYNC_ORDER[$baseName] ?? 1000,
                ];
            }
        }

        usort($models, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $models;
    }

    /**
     * @param null|callable(string $status, string $model, string $table, int $index, int $total): void $onProgress
     * @return array{created: string[], updated: string[], skipped: string[], failed: list<array{table: string, error: string}>}
     */
    public function sync(?string $modelName = null, ?callable $onProgress = null): array
    {
        $result = ['created' => [], 'updated' => [], 'skipped' => [], 'failed' => []];
        $models = $this->discover($modelName);
        $total = count($models);

        if ($models === []) {
            return $result;
        }

        foreach ($models as $index => $model) {
            $table = (string) ($model['schema']['table'] ?? '');
            $schema = $model['schema'];
            $modelNameLabel = (string) ($model['name'] ?? 'unknown');
            if ($onProgress) {
                $onProgress('start', $modelNameLabel, $table, $index + 1, $total);
            }

            try {
                if ($table === '') {
                    throw new \RuntimeException('tableSchema 缺少 table');
                }

                if (!DB::schema()->hasTable($table)) {
                    DB::schema()->create($table, function ($blueprint) use ($schema) {
                        SchemaBlueprintBuilder::apply($blueprint, $schema, true);
                    });
                    $result['created'][] = $table;
                    if ($onProgress) {
                        $onProgress('created', $modelNameLabel, $table, $index + 1, $total);
                    }
                    continue;
                }

                $structureChanged = $this->tableNeedsUpdate($schema);
                $commentChanged = SchemaCommentSynchronizer::commentsNeedUpdate($schema);

                if ($structureChanged) {
                    DB::schema()->table($table, function ($blueprint) use ($schema) {
                        SchemaBlueprintBuilder::apply($blueprint, $schema, false);
                    });
                    SchemaCommentSynchronizer::clearCache($table);
                }

                if ($commentChanged) {
                    SchemaCommentSynchronizer::sync($schema);
                }

                if ($structureChanged || $commentChanged) {
                    $result['updated'][] = $table;
                    if ($onProgress) {
                        $onProgress('updated', $modelNameLabel, $table, $index + 1, $total);
                    }
                } else {
                    $result['skipped'][] = $table;
                    if ($onProgress) {
                        $onProgress('skipped', $modelNameLabel, $table, $index + 1, $total);
                    }
                }
            } catch (\Throwable $e) {
                $failedTable = $table !== '' ? $table : $modelNameLabel;
                $result['failed'][] = [
                    'table' => $failedTable,
                    'error' => $e->getMessage(),
                ];
                if ($onProgress) {
                    $onProgress('failed', $modelNameLabel, $failedTable, $index + 1, $total);
                }
            }
        }

        return $result;
    }

    private function tableNeedsUpdate(array $schema): bool
    {
        $table = $schema['table'];
        $existingColumns = $this->listColumns($table);
        $existingIndexes = $this->listIndexes($table);

        foreach ($schema['columns'] ?? [] as $name => $definition) {
            if (!isset($existingColumns[$name])) {
                return true;
            }
        }

        foreach ($schema['indexes'] ?? [] as $index) {
            $columns = $index['columns'] ?? [];
            if ($columns === []) {
                continue;
            }
            $name = $index['name'] ?? SchemaBlueprintBuilder::indexName($table, $columns);
            if (!isset($existingIndexes[$name])) {
                return true;
            }
        }

        foreach ($schema['unique'] ?? [] as $unique) {
            $columns = $unique['columns'] ?? [];
            if ($columns === []) {
                continue;
            }
            $name = $unique['name'] ?? SchemaBlueprintBuilder::uniqueName($table, $columns);
            if (!isset($existingIndexes[$name])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private function listColumns(string $table): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->COLUMN_NAME] = true;
        }

        return $map;
    }

    /** @return array<string, true> */
    private function listIndexes(string $table): array
    {
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->INDEX_NAME] = true;
        }

        return $map;
    }

    private function guessTableName(string $baseName): string
    {
        $name = str_ends_with($baseName, 'Model') ? substr($baseName, 0, -5) : $baseName;

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name) . 's';
    }

    public static function normalizeModelClassName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $name = (string) end($parts);
        }
        $name = ucfirst($name);

        return str_ends_with($name, 'Model') ? $name : $name . 'Model';
    }
}
