<?php

declare(strict_types=1);

namespace app\middleware;

use app\model\pay\MerchantModel;
use app\service\pay\MerchantPortalService;
use app\support\ErrorCode;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class MerchantPortalAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return fail(ErrorCode::UNAUTHORIZED);
        }

        $jwt = substr($token, 7);
        $service = new MerchantPortalService();
        $decoded = $service->decodeAccessToken($jwt);

        if (!$decoded || empty($decoded->merchant_id)) {
            return fail(ErrorCode::TOKEN_INVALID);
        }

        $merchant = MerchantModel::find((int) $decoded->merchant_id);
        if (!$merchant || (int) $merchant->status !== 1) {
            return fail(ErrorCode::PAY_MERCHANT_DISABLED);
        }

        $request->merchant_id = (int) $merchant->id;
        $request->merchant = $merchant;

        return $next($request);
    }
}
