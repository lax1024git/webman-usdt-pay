<?php

declare(strict_types=1);

namespace app\support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * 应用时区统一入口：PHP date_* 与 MySQL session time_zone 必须一致。
 *
 * MySQL 使用固定偏移（如 +08:00），避免依赖 mysql 时区表；
 * 业务墙钟时间字段应使用 DATETIME，避免 TIMESTAMP 随 session 时区改写。
 */
final class AppTimezone
{
    public static function name(): string
    {
        $tz = trim((string) env('APP_TIMEZONE', ''));
        if ($tz === '' && function_exists('config')) {
            try {
                $tz = (string) config('app.default_timezone', 'Asia/Shanghai');
            } catch (Throwable) {
                $tz = 'Asia/Shanghai';
            }
        }
        if ($tz === '') {
            $tz = 'Asia/Shanghai';
        }

        try {
            new DateTimeZone($tz);

            return $tz;
        } catch (Throwable) {
            return 'Asia/Shanghai';
        }
    }

    /**
     * MySQL session 时区，优先 DB_TIMEZONE；否则由 APP_TIMEZONE 推导为 ±HH:MM。
     */
    public static function mysqlOffset(): string
    {
        $configured = trim((string) env('DB_TIMEZONE', ''));
        if ($configured !== '') {
            if (preg_match('/^[+-]\d{2}:\d{2}$/', $configured) === 1) {
                return $configured;
            }
            try {
                return (new DateTimeImmutable('now', new DateTimeZone($configured)))->format('P');
            } catch (Throwable) {
                // fall through
            }
        }

        try {
            return (new DateTimeImmutable('now', new DateTimeZone(self::name())))->format('P');
        } catch (Throwable) {
            return '+08:00';
        }
    }

    public static function applyPhp(): void
    {
        date_default_timezone_set(self::name());
    }

    public static function applyToConnection(\Illuminate\Database\Connection $connection): void
    {
        $offset = self::mysqlOffset();
        // 用字面量偏移，兼容不支持预处理 SET 的驱动
        $connection->statement("SET time_zone = '{$offset}'");
    }
}
