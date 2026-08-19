<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use app\model\sys\SettingModel;
use support\Redis;

class SettingService
{
    public function list(): array
    {
        return SettingModel::orderBy('id')->get()->toArray();
    }

    public function get(string $key): SettingModel
    {
        $setting = SettingModel::where('key', $key)->first();
        if (!$setting) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
        }
        return $setting;
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";
        try {
            $cached = Redis::get($cacheKey);
            if ($cached !== false && $cached !== null && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->unwrapValue($decoded);
                }
            }
        } catch (\Throwable) {
            // Redis 不可用时回退数据库
        }

        $setting = SettingModel::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $value = $this->unwrapValue($setting->value);
        try {
            Redis::setex($cacheKey, 86400, json_encode($value, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // ignore cache write failure
        }

        return $value;
    }

    /**
     * 兼容 value 被存成 JSON 字符串 / 双重编码 的情况
     */
    private function unwrapValue(mixed $value): mixed
    {
        for ($i = 0; $i < 2; $i++) {
            if (!is_string($value)) {
                break;
            }
            $trim = trim($value);
            if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[' && $trim[0] !== '"')) {
                break;
            }
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $value = $decoded;
        }

        return $value;
    }

    /**
     * 按候选 key 依次读取，返回第一个非空配置
     *
     * @param list<string> $keys
     */
    public function getValueFirst(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = $this->getValue($key, null);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            return $value;
        }

        return $default;
    }

    /**
     * @return array{
     *     credentials_key: string,
     *     credentials_secret: string,
     *     region: string,
     *     bucket: string,
     *     url: string,
     *     proxy: string|null,
     *     presign_expires: int
     * }
     */
    public function getS3Config(): array
    {
        $config = $this->getValue('s3_config', []);
        if (!is_array($config)) {
            $config = [];
        }

        return [
            'credentials_key' => (string) ($config['credentials_key'] ?? ''),
            'credentials_secret' => (string) ($config['credentials_secret'] ?? ''),
            'region' => (string) ($config['region'] ?? 'ap-east-1'),
            'bucket' => (string) ($config['bucket'] ?? ''),
            'url' => rtrim((string) ($config['url'] ?? ''), '/'),
            'proxy' => isset($config['proxy']) && $config['proxy'] !== '' ? (string) $config['proxy'] : null,
            'presign_expires' => max(60, (int) ($config['presign_expires'] ?? 900)),
        ];
    }

    public function update(string $key, mixed $value): SettingModel
    {
        $setting = SettingModel::where('key', $key)->first();
        if (!$setting) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
        }
        $setting->update(['value' => $value]);
        try {
            Redis::del("setting:{$key}");
        } catch (\Throwable) {
        }
        return $setting;
    }

    public function batchUpdate(array $settings): void
    {
        foreach ($settings as $item) {
            $this->update($item['key'], $item['value']);
        }
    }

    public function upsert(string $key, mixed $value, string $description = ''): SettingModel
    {
        $setting = SettingModel::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
            try {
                Redis::del("setting:{$key}");
            } catch (\Throwable) {
            }

            return $setting;
        }

        $setting = SettingModel::create([
            'key' => $key,
            'value' => $value,
            'description' => $description,
        ]);
        try {
            Redis::del("setting:{$key}");
        } catch (\Throwable) {
        }

        return $setting;
    }
}
