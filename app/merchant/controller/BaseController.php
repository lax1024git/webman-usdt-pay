<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\model\pay\MerchantModel;
use support\Request;

abstract class BaseController
{
    protected function pageParams(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        return [$page, $limit];
    }

    protected function merchant(Request $request): MerchantModel
    {
        return $request->merchant;
    }

    protected function merchantId(Request $request): int
    {
        return (int) ($request->merchant_id ?? 0);
    }
}
