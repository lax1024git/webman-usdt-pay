<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\pay\MerchantService;
use app\support\ErrorCode;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class MerchantAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $apiKey = (string) $request->header('X-Pay-Key', '');
        $timestamp = (string) $request->header('X-Pay-Timestamp', '');
        $nonce = (string) $request->header('X-Pay-Nonce', '');
        $signature = (string) $request->header('X-Pay-Signature', '');

        if ($apiKey === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return fail(ErrorCode::PAY_SIGNATURE_INVALID, '缺少签名头');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return fail(ErrorCode::PAY_SIGNATURE_INVALID, '时间戳过期');
        }

         $merchantService = new MerchantService();
        try {
            $merchant = $merchantService->findByApiKey($apiKey);
        } catch (\Throwable) {
            return fail(ErrorCode::PAY_MERCHANT_DISABLED);
        }

        $whitelist = $merchant->ip_whitelist ?? [];
        if (is_array($whitelist) && $whitelist !== []) {
            $ip = (string) $request->getRealIp();
            if (!in_array($ip, $whitelist, true)) {
                return fail(ErrorCode::PAY_IP_NOT_ALLOWED);
            }
        }

        $plainSecret = $merchantService->getPlainSecret((int) $merchant->id);
        $method = strtoupper($request->method());
        $path = '/' . ltrim((string) $request->path(), '/');
        $body = (string) $request->rawBody();
        $payload = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $body;
        $expected = hash_hmac('sha256', $payload, $plainSecret);

        if (!hash_equals($expected, strtolower($signature))) {
            return fail(ErrorCode::PAY_SIGNATURE_INVALID);
        }

        $request->merchant_id = (int) $merchant->id;
        $request->merchant = $merchant;

        return $next($request);
    }
}
