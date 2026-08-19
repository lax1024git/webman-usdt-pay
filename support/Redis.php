<?php

declare(strict_types=1);

namespace support;

class Redis
{
    protected static ?\Redis $instance = null;

    public static function connection(): \Redis
    {
        if (static::$instance === null) {
            $config = config('redis.default');
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port'], $config['timeout'] ?? 2);
            if (!empty($config['auth'])) {
                $redis->auth($config['auth']);
            }
            if (isset($config['database'])) {
                $redis->select((int) $config['database']);
            }
            $prefix = (string) ($config['prefix'] ?? '');
            if ($prefix !== '') {
                $redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }
            static::$instance = $redis;
        }
        return static::$instance;
    }

    public static function get(string $key): mixed
    {
        $value = static::connection()->get($key);
        return $value === false ? null : $value;
    }

    public static function setex(string $key, int $ttl, mixed $value): bool
    {
        return static::connection()->setex($key, $ttl, (string) $value);
    }

    public static function del(string ...$keys): int
    {
        if ($keys === []) {
            return 0;
        }
        return static::connection()->del(...$keys);
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $redis = static::connection();
        if ($ttl !== null && $ttl > 0) {
            return $redis->setex($key, $ttl, (string) $value);
        }
        return $redis->set($key, (string) $value);
    }

    public static function setNx(string $key, mixed $value, int $ttl): bool
    {
        return (bool) static::connection()->set($key, (string) $value, ['nx', 'ex' => $ttl]);
    }

    public static function incr(string $key): int
    {
        return (int) static::connection()->incr($key);
    }

    public static function expire(string $key, int $ttl): bool
    {
        return static::connection()->expire($key, $ttl);
    }

    public static function ttl(string $key): int
    {
        return (int) static::connection()->ttl($key);
    }

    public static function exists(string $key): bool
    {
        return (bool) static::connection()->exists($key);
    }

    public static function rpush(string $key, mixed ...$values): int
    {
        return (int) static::connection()->rPush($key, ...array_map('strval', $values));
    }

    public static function lpop(string $key): ?string
    {
        $value = static::connection()->lPop($key);
        return $value === false ? null : (string) $value;
    }

    /** @return string[] 返回不含 OPT_PREFIX 的业务 key，便于再走 del/get */
    public static function keys(string $pattern): array
    {
        $keys = static::connection()->keys($pattern);
        if (!is_array($keys)) {
            return [];
        }
        $prefix = (string) (config('redis.default.prefix') ?? '');
        $prefixLen = strlen($prefix);
        return array_map(static function ($key) use ($prefix, $prefixLen): string {
            $key = (string) $key;
            if ($prefixLen > 0 && str_starts_with($key, $prefix)) {
                return substr($key, $prefixLen);
            }
            return $key;
        }, $keys);
    }
}
