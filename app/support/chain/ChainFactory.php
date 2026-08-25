<?php

declare(strict_types=1);

namespace app\support\chain;

use app\exception\BusinessException;
use app\support\Decimal;
use app\support\ErrorCode;

class ChainFactory
{
    public static function make(string $chain): ChainAdapterInterface
    {
        return match (strtoupper($chain)) {
            'TRC20', 'TRON' => new TronAdapter(),
            default => throw new BusinessException(ErrorCode::PAY_PLATFORM_UNAVAILABLE, '暂不支持的链: ' . $chain),
        };
    }
}
