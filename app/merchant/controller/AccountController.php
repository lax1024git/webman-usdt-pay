<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\service\pay\LedgerService;
use app\service\pay\PlatformService;
use support\Request;

class AccountController extends BaseController
{
    public function balance(Request $request)
    {
        $merchant = $this->merchant($request);
        $platformCode = (string) $request->get('platform_code', 'TRC20_USDT');
        $platform = (new PlatformService())->getByCode($platformCode);

        return success((new LedgerService())->getBalance(
            (int) $merchant->id,
            (string) $platform->currency,
            (string) $platform->chain
        ));
    }
}
