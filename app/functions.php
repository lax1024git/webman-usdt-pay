<?php

declare(strict_types=1);

use support\Response;
use app\support\ErrorCode;

function json_response(array $data, int $status = 200)
{
    if (isset($data['msg']) && is_string($data['msg']) && $data['msg'] !== '') {
        $data['msg'] = app_lang($data['msg']);
    }
    if (array_key_exists('data', $data)) {
        $data['data'] = translate_api_payload($data['data']);
    }

    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
}

function success(mixed $data = null, string $msg = 'success')
{
    // 无业务数据时统一返回空数组，避免前端收到 data: null
    if ($data === null) {
        $data = [];
    }

    return json_response(['code' => ErrorCode::OK, 'msg' => $msg, 'data' => $data]);
}

function fail(int $code, ?string $msg = null, int $httpStatus = 200)
{
    return json_response(['code' => $code, 'msg' => ErrorCode::message($code, $msg), 'data' => []], $httpStatus);
}

/**
 * 翻译接口 data 中的状态/标签类文案字段（*_text / *_label 等）。
 * money_log 额外翻译 change_str / detail_type_str / type_str；不翻译通用 title。
 */
function translate_api_payload(mixed $value, ?bool $isMoneyLog = null): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if ($isMoneyLog === null) {
        $isMoneyLog = false;
        if (function_exists('request')) {
            $req = request();
            if ($req) {
                $path = '/' . ltrim((string) $req->path(), '/');
                $isMoneyLog = str_ends_with($path, '/money_log') || str_contains($path, '/money_log/');
            }
        }
    }

    $out = [];
    foreach ($value as $k => $v) {
        if (is_array($v)) {
            $out[$k] = translate_api_payload($v, $isMoneyLog);
            continue;
        }
        if (!is_string($v) || $v === '') {
            $out[$k] = $v;
            continue;
        }

        $shouldTranslate = is_string($k) && (
            $k === 'msg'
            || $k === 'message'
            || str_ends_with($k, '_text')
            || str_ends_with($k, '_label')
            || in_array($k, ['tip', 'tips', 'button_text'], true)
            || ($isMoneyLog && in_array($k, ['change_str', 'detail_type_str', 'type_str'], true))
        );

        $out[$k] = $shouldTranslate ? app_lang($v) : $v;
    }

    return $out;
}

/**
 * 按当前请求语言翻译文案（键通常为中文源文本）。
 * 非中文环境缺失时回落：当前语言 → 英文 → 西语 → 原文。
 */
function app_lang(string $key, ?string $default = null): string
{
    if ($key === '') {
        return $default ?? '';
    }

    static $cache = [];
    $locale = \app\support\Locale::current();

    $load = static function (string $loc) use (&$cache): array {
        // 按语言包 mtime 失效，后台导出后无需重启 worker 也能生效
        $mtime = 0;
        foreach (\app\support\Locale::aliases($loc) as $code) {
            $base = str_replace('-', '_', strtolower($code));
            foreach ([
                base_path("resource/translations/{$base}.php"),
                base_path("resource/translations/overlays/{$base}.php"),
            ] as $path) {
                if (is_file($path)) {
                    $mtime = max($mtime, (int) filemtime($path));
                }
            }
        }
        $cacheKey = $loc . '@' . $mtime;
        if (!isset($cache[$cacheKey])) {
            foreach (array_keys($cache) as $k) {
                if (str_starts_with((string) $k, $loc . '@')) {
                    unset($cache[$k]);
                }
            }
            $cache[$cacheKey] = (new \app\service\LangExportService())->loadForLocale($loc);
        }
        return $cache[$cacheKey];
    };

    $map = $load($locale);
    if (isset($map[$key]) && $map[$key] !== '') {
        return $map[$key];
    }

    // 前端 API：缺失的中文文案键自动登记到 sy_lang_items（type=front），便于后台翻译
    ensure_front_lang_item($key);

    if (!str_starts_with($locale, 'zh')) {
        foreach (['en', 'es'] as $fallback) {
            if ($fallback === $locale) {
                continue;
            }
            $fb = $load($fallback);
            if (isset($fb[$key]) && $fb[$key] !== '') {
                return $fb[$key];
            }
        }
    }

    return $default ?? $key;
}

/**
 * 前端接口返回中文 msg 时，若 sy_lang_items 尚无该键则自动入库（type=front）。
 */
function ensure_front_lang_item(string $key): void
{
    static $seen = [];

    $key = trim($key);
    if ($key === '' || isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;

    // 仅收集含中文的文案键，跳过 success / error 等英文
    if (!preg_match('/\p{Han}/u', $key)) {
        return;
    }
    if (mb_strlen($key) > 500) {
        return;
    }

    try {
        $path = '';
        if (function_exists('request')) {
            $req = request();
            if ($req) {
                $path = '/' . ltrim((string) $req->path(), '/');
            }
        }
        // 仅前端 /api 接口自动收集；后台 /admin 不写
        if ($path === '' || !str_starts_with($path, '/api')) {
            return;
        }

        \app\model\sys\LangItemModel::firstOrCreate(
            [
                'title' => $key,
                'type' => \app\model\sys\LangItemModel::TYPE_FRONT,
            ],
            [
                'title' => $key,
                'type' => \app\model\sys\LangItemModel::TYPE_FRONT,
            ]
        );
    } catch (\Throwable) {
        // 收集失败不影响接口响应
    }
}

/**
 * IP 转地区（兼容参考项目 ip2location）。
 */
function ip2location(string $ip): string
{
    return \app\support\IpLocationResolver::resolve($ip);
}

/**
 * IP 转地区结构化数据（兼容参考项目 ip2location_arr）。
 *
 * @return array<string, string>
 */
function ip2location_arr(string $ip): array
{
    return \app\support\IpLocationResolver::resolveDetail($ip);
}
