<?php

declare(strict_types=1);

namespace app\middleware;

use app\support\Locale;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class LocaleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $headerLang = (string) ($request->header('X-App-Lang') ?: '');
        // Accept-Language 仅作兜底（浏览器默认语言），避免干扰显式切换
        if ($headerLang === '') {
            $headerLang = (string) ($request->header('Accept-Language') ?: '');
            if ($headerLang !== '' && str_contains($headerLang, ',')) {
                $headerLang = trim(explode(',', $headerLang)[0]);
            }
            if ($headerLang !== '' && str_contains($headerLang, ';')) {
                $headerLang = trim(explode(';', $headerLang)[0]);
            }
        }

        $cookieLang = (string) ($request->cookie('app_lang') ?? '');
        $queryLang = (string) ($request->get('lang', ''));
        $userLang = isset($request->user) && is_object($request->user)
            ? (string) ($request->user->lang ?? '')
            : null;

        $path = '/' . ltrim((string) $request->path(), '/');
        // 管理后台始终允许按请求语言切换（不受用户端 checkout_language 限制）
        $isAdmin = str_starts_with($path, '/admin');

        $locale = Locale::resolveFromRequest(
            $queryLang !== '' ? $queryLang : ($headerLang !== '' ? $headerLang : null),
            $cookieLang !== '' ? $cookieLang : null,
            $userLang,
            $isAdmin
        );
        Locale::setCurrent($locale);
        $request->locale = $locale;

        /** @var Response $response */
        $response = $handler($request);
        return $response;
    }
}
