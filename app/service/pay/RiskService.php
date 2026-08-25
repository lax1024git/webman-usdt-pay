<?php

declare(strict_types=1);

namespace app\service\pay;

use app\exception\BusinessException;
use app\model\pay\AddressBlacklistModel;
use app\model\pay\PlatformModel;
use app\support\Decimal;
use app\support\ErrorCode;
use app\support\chain\ChainFactory;

class RiskService
{
    public function validateWithdrawAddress(string $chain, string $address): void
    {
        $adapter = ChainFactory::make($chain);
        if (!$adapter->validateAddress($address)) {
            throw new BusinessException(ErrorCode::PAY_ADDRESS_INVALID);
        }

        $exists = AddressBlacklistModel::query()
            ->where('chain', $chain)
            ->where('address', $address)
            ->exists();
        if ($exists) {
            throw new BusinessException(ErrorCode::PAY_ADDRESS_BLACKLISTED);
        }
    }

    public function validateAmount(PlatformModel $platform, string $amount, string $type): void
    {
        $amount = Decimal::format($amount);
        if (!Decimal::isPositive($amount)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '金额必须大于 0');
        }

        if ($type === 'deposit') {
            $min = (string) $platform->min_deposit_amount;
            $max = (string) $platform->max_deposit_amount;
        } else {
            $min = (string) $platform->min_withdraw_amount;
            $max = (string) $platform->max_withdraw_amount;
        }

        if (Decimal::cmp($min, '0') > 0 && Decimal::cmp($amount, $min) < 0) {
            throw new BusinessException(ErrorCode::PAY_AMOUNT_TOO_LOW);
        }
        if (Decimal::cmp($max, '0') > 0 && Decimal::cmp($amount, $max) > 0) {
            throw new BusinessException(ErrorCode::PAY_AMOUNT_TOO_HIGH);
        }
    }

    public function calcWithdrawFee(string $amount, string $rate, string $min, string $max): string
    {
        $fee = Decimal::mul($amount, $rate);
        if (Decimal::cmp($min, '0') > 0 && Decimal::cmp($fee, $min) < 0) {
            $fee = $min;
        }
        if (Decimal::cmp($max, '0') > 0 && Decimal::cmp($fee, $max) > 0) {
            $fee = $max;
        }

        return Decimal::format($fee);
    }
}
