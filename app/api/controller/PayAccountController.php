<?php

declare(strict_types=1);

namespace app\api\controller;

use app\model\pay\MerchantModel;
use app\service\pay\LedgerService;
use app\service\pay\PlatformService;
use support\Request;

class PayAccountController
{
    public function balance(Request $request)
    {
        /** @var MerchantModel $merchant */
        $merchant = $request->merchant;
        $platformCode = (string) $request->get('platform_code', 'TRC20_USDT');
        $platform = (new PlatformService())->getByCode($platformCode);

        return success((new LedgerService())->getBalance(
            (int) $merchant->id,
            (string) $platform->currency,
            (string) $platform->chain
        ));
    }
}
