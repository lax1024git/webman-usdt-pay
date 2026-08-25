<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\service\pay\MerchantPortalService;
use support\Request;

class SettingsController extends BaseController
{
    public function show(Request $request)
    {
        $merchant = $this->merchant($request);
        return success([
            'notify_url' => $merchant->notify_url,
            'ip_whitelist' => $merchant->ip_whitelist ?? [],
            'api_key' => $merchant->api_key,
        ]);
    }

    public function update(Request $request)
    {
        $merchant = $this->merchant($request);
        $service = new MerchantPortalService();
        $updated = $service->updateSettings($merchant, $request->post());
        return success([
            'notify_url' => $updated->notify_url,
            'ip_whitelist' => $updated->ip_whitelist ?? [],
        ], '更新成功');
    }

    public function resetSecret(Request $request)
    {
        $merchant = $this->merchant($request);
        $loginPassword = (string) $request->post('login_password', '');
        $result = (new MerchantPortalService())->resetSecret($merchant, $loginPassword);
        return success($result, 'API Secret 已重置，请立即保存');
    }
}
