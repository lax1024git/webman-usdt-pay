<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\service\pay\MerchantPortalService;
use support\Request;

class AuthController extends BaseController
{
    protected MerchantPortalService $service;

    public function __construct(?MerchantPortalService $service = null)
    {
        $this->service = $service ?? new MerchantPortalService();
    }

    public function login(Request $request)
    {
        $email = (string) $request->post('email', '');
        $password = (string) $request->post('password', '');
        $ip = (string) $request->getRealIp();

        return success($this->service->login($email, $password, $ip));
    }

    public function refresh(Request $request)
    {
        $refreshToken = (string) $request->post('refresh_token', '');
        return success($this->service->refresh($refreshToken));
    }

    public function logout(Request $request)
    {
        $refreshToken = (string) $request->post('refresh_token', '');
        $this->service->logout($refreshToken);
        return success([], '已退出');
    }

    public function me(Request $request)
    {
        $merchant = $this->merchant($request);
        return success([
            'id' => $merchant->id,
            'merchant_no' => $merchant->merchant_no,
            'name' => $merchant->name,
            'login_email' => $merchant->login_email,
            'notify_url' => $merchant->notify_url,
            'status' => $merchant->status,
            'deposit_fee_rate' => $merchant->deposit_fee_rate,
            'withdraw_fee_rate' => $merchant->withdraw_fee_rate,
            'created_at' => (string) $merchant->created_at,
            'last_login_at' => $merchant->last_login_at,
        ]);
    }

    public function changePassword(Request $request)
    {
        $merchant = $this->merchant($request);
        $oldPassword = (string) $request->post('old_password', '');
        $newPassword = (string) $request->post('new_password', '');
        $this->service->changePassword($merchant, $oldPassword, $newPassword);
        return success([], '密码修改成功');
    }
}
