<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use app\support\SystemConfigMeta;

class SystemConfigService
{
    private SettingService $settingService;

  /** @var array<string, mixed>|null */
    private static ?array $schemaCache = null;

    public function __construct(?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

  /** @return array{tabs: list<array<string, mixed>>, options: array<string, mixed>, values: array<string, mixed>, client_ip: string} */
    public function bundle(): array
    {
        $schema = $this->schema();
        $keys = $this->collectKeys($schema['tabs']);
        $values = [];
        foreach ($keys as $key) {
            if ($this->isHiddenKey($schema['tabs'], $key)) {
                continue;
            }
            $default = $this->defaultForKey($schema['tabs'], $key);
            $values[$key] = $this->normalizeValue($key, $this->settingService->getValue($key, $default));
        }

        return [
            'tabs' => $this->hydrateTabs($schema['tabs']),
            'options' => SystemConfigMeta::optionSets(),
            'values' => $values,
            'client_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
    }

  /** @param array<string, mixed> $payload */
    public function save(array $payload): void
    {
        $schema = $this->schema();
        $allowed = array_fill_keys($this->collectKeys($schema['tabs']), true);

        foreach ($payload as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }
            $value = $this->coerceForSave($schema['tabs'], (string) $key, $value);
            $this->settingService->upsert($key, $value, $this->descriptionForKey($schema['tabs'], $key));
        }
    }

  /** @return array<string, mixed> */
    public function schema(): array
    {
        if (self::$schemaCache !== null) {
            return self::$schemaCache;
        }

        $path = base_path('config/system_config_schema.php');
        if (!is_file($path)) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '系统配置 schema 不存在');
        }

        /** @var array<string, mixed> $schema */
        $schema = require $path;
        self::$schemaCache = $schema;

        return $schema;
    }

  /** @param list<array<string, mixed>> $tabs */
    private function collectKeys(array $tabs): array
    {
        $keys = [];
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                $keys[] = (string) $field['key'];
            }
        }

        return array_values(array_unique($keys));
    }

  /** @param list<array<string, mixed>> $tabs */
    private function defaultForKey(array $tabs, string $key): mixed
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    return $field['default'] ?? '';
                }
            }
        }

        return '';
    }

  /** @param list<array<string, mixed>> $tabs */
    private function descriptionForKey(array $tabs, string $key): string
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    $help = (string) ($field['help'] ?? '');
                    $label = (string) ($field['label'] ?? $key);

                    return $help !== '' ? "{$label}（{$help}）" : $label;
                }
            }
        }

        return $key;
    }

  /** @param list<array<string, mixed>> $tabs */
    private function typeForKey(array $tabs, string $key): string
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    return (string) ($field['type'] ?? 'text');
                }
            }
        }

        return 'text';
    }

  /** @param list<array<string, mixed>> $tabs */
    private function coerceForSave(array $tabs, string $key, mixed $value): mixed
    {
        if ($this->typeForKey($tabs, $key) !== 'json' || !is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        throw new BusinessException(ErrorCode::VALIDATION_FAILED, "配置 {$key} 不是合法 JSON");
    }

  /** @param list<array<string, mixed>> $tabs */
    private function hydrateTabs(array $tabs): array
    {
        $options = SystemConfigMeta::optionSets();
        $result = [];
        foreach ($tabs as $tab) {
            $fields = [];
            foreach ($tab['fields'] ?? [] as $field) {
                if (!empty($field['hidden'])) {
                    continue;
                }
                $item = $field;
                if (!empty($field['options_ref'])) {
                    $ref = (string) $field['options_ref'];
                    $item['options'] = $options[$ref] ?? [];
                    unset($item['options_ref']);
                }
                unset($item['hidden']);
                $fields[] = $item;
            }
            $tab['fields'] = $fields;
            $result[] = $tab;
        }

        return $result;
    }

  /** @param list<array<string, mixed>> $tabs */
    private function isHiddenKey(array $tabs, string $key): bool
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    return !empty($field['hidden']);
                }
            }
        }

        return false;
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if ($key === 's3_config') {
            if (!is_array($value)) {
                return [
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ];
            }

            return [
                'credentials_key' => (string) ($value['credentials_key'] ?? ''),
                'credentials_secret' => (string) ($value['credentials_secret'] ?? ''),
                'region' => (string) ($value['region'] ?? 'ap-east-1'),
                'bucket' => (string) ($value['bucket'] ?? ''),
                'url' => (string) ($value['url'] ?? ''),
                'proxy' => isset($value['proxy']) && $value['proxy'] !== '' ? (string) $value['proxy'] : null,
                'presign_expires' => max(60, (int) ($value['presign_expires'] ?? 900)),
            ];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return (string) $value;
    }
}
