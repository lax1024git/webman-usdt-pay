<?php

declare(strict_types=1);

namespace app\support\pay;

use support\Redis;

final class OrderNoGenerator
{
    public static function deposit(): string
    {
        return self::next('D');
    }

    public static function withdraw(): string
    {
        return self::next('W');
    }

    public static function merchantNo(): string
    {
        $seq = self::increment('merchant');
        return 'M' . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }

    private static function next(string $prefix): string
    {
        $minute = date('YmdHi');
        $seq = self::increment(strtolower($prefix) . ':' . $minute);

        return $prefix . $minute . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    private static function increment(string $key): int
    {
        try {
            return (int) Redis::incr('pay:seq:' . $key);
        } catch (\Throwable) {
            return random_int(1, 999999);
        }
    }
}
