<?php

declare(strict_types=1);

namespace app\support;

final class Decimal
{
    public const SCALE = 6;

    public static function add(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcadd(self::normalize($a), self::normalize($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcsub(self::normalize($a), self::normalize($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcmul(self::normalize($a), self::normalize($b), $scale);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        if (bccomp(self::normalize($b), '0', $scale) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }

        return bcdiv(self::normalize($a), self::normalize($b), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = self::SCALE): int
    {
        return bccomp(self::normalize($a), self::normalize($b), $scale);
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::format($a) : self::format($b);
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::format($a) : self::format($b);
    }

    public static function format(string $value, int $scale = self::SCALE): string
    {
        return bcadd(self::normalize($value), '0', $scale);
    }

    public static function isPositive(string $value): bool
    {
        return self::cmp($value, '0') > 0;
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0';
        }

        return $value;
    }
}
