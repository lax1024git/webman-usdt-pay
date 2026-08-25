<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\service\pay\LedgerService;
use support\Request;

class LedgerController extends BaseController
{
    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success((new LedgerService())->listLedgers(
            $this->merchantId($request),
            $page,
            $limit,
            $request->get()
        ));
    }
}
