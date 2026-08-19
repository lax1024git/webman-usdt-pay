<?php

declare(strict_types=1);

namespace app\support;

use app\model\sys\LangModel;
use app\service\SettingService;
use support\Context;

/**
 * 统一语言解析：H5 vue-i18n 代码与 API/DB 语言代码映射。
 */
class Locale
{
    public const CONTEXT_KEY = 'request_locale';

    /** vue-i18n / 前端简码 → API/DB 语言代码 */
    private const FRONT_TO_API = [
        'cn' => 'zh-cn',
        'zh' => 'zh-cn',
        'zh-cn' => 'zh-cn',
        'zh_cn' => 'zh-cn',
        'zh-CN' => 'zh-cn',
        'zh-tw' => 'zh-tw',
        'zh-TW' => 'zh-tw',
        'pt' => 'pt',
        'en' => 'en',
        'en-us' => 'en',
        'en_us' => 'en',
        'en-gb' => 'en',
        'en_gb' => 'en',
        'es' => 'es',
        'ja' => 'ja',
        'jp' => 'ja',
        'ja-jp' => 'ja',
        'ko' => 'ko',
        'kr' => 'ko',
        'ko-kr' => 'ko',
        'id' => 'id',
        'id-id' => 'id',
        'ms' => 'ms',
        'ms-my' => 'ms',
    ];

    /** API/DB 语言代码 → vue-i18n 简码 */
    private const API_TO_FRONT = [
        'zh-cn' => 'cn',
        'zh-tw' => 'cn',
        'pt' => 'pt',
        'en' => 'en',
        'en-us' => 'en',
        'es' => 'es',
        'ja' => 'ja',
        'ko' => 'ko',
        'id' => 'id',
        'ms' => 'ms',
    ];

    /** 同一语言在 DB / 文件名中可能出现的别名 */
    private const ALIASES = [
        'en' => ['en', 'en-us', 'en_us', 'en-gb', 'en_gb'],
        'zh-cn' => ['zh-cn', 'zh_cn', 'cn', 'zh'],
        'zh-tw' => ['zh-tw', 'zh_tw'],
        'pt' => ['pt', 'pt-br', 'pt_br'],
        'es' => ['es', 'es-es', 'es_es'],
        'ja' => ['ja', 'jp', 'ja-jp', 'ja_jp'],
        'ko' => ['ko', 'kr', 'ko-kr', 'ko_kr'],
        'id' => ['id', 'id-id', 'id_id'],
        'ms' => ['ms', 'ms-my', 'ms_my'],
    ];

    public static function normalize(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return self::defaultApiLocale();
        }
        return self::FRONT_TO_API[$code] ?? $code;
    }

    public static function toFrontCode(string $apiCode): string
    {
        $apiCode = self::normalize($apiCode);
        return self::API_TO_FRONT[$apiCode] ?? 'cn';
    }

    /**
     * @return list<string>
     */
    public static function aliases(string $apiCode): array
    {
        $apiCode = self::normalize($apiCode);
        return self::ALIASES[$apiCode] ?? [$apiCode];
    }

    public static function defaultApiLocale(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $fromSetting = (new SettingService())->getValue('system_default_lang', '');
        if (is_string($fromSetting) && $fromSetting !== '') {
            $cached = self::normalize($fromSetting);
            return $cached;
        }

        $row = LangModel::where('is_default_lang', 1)->where('status', 1)->first();
        if ($row) {
            $cached = self::normalize((string) $row->lang);
            return $cached;
        }

        $cached = 'zh-cn';
        return $cached;
    }

    public static function resolveFromRequest(
        ?string $queryLang,
        ?string $cookieLang,
        ?string $userLang = null,
        bool $ignoreCheckoutGate = false
    ): string {
        foreach ([$queryLang, $cookieLang, $userLang] as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                $normalized = self::normalize($candidate);
                if (self::isEnabled($normalized)) {
                    return $normalized;
                }
            }
        }

        return self::defaultApiLocale();
    }

    public static function isEnabled(string $apiCode): bool
    {
        $apiCode = self::normalize($apiCode);
        $aliases = self::aliases($apiCode);

        if (LangModel::whereIn('lang', $aliases)->where('status', 1)->where('switch_enabled', 1)->exists()) {
            return true;
        }

        // 语言表未配置启用项时：有语言包文件也视为可用（避免 en 被静默回落成中文）
        if (!LangModel::where('status', 1)->exists()) {
            return self::hasLanguagePack($apiCode);
        }

        return false;
    }

    public static function hasLanguagePack(string $apiCode): bool
    {
        foreach (self::aliases($apiCode) as $code) {
            $base = str_replace('-', '_', strtolower($code));
            if (
                is_file(base_path("resource/translations/{$base}.php"))
                || is_file(base_path("resource/translations/overlays/{$base}.php"))
            ) {
                return true;
            }
        }
        return false;
    }

    public static function enabledCodes(): array
    {
        return LangModel::where('status', 1)
            ->where('switch_enabled', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck('lang')
            ->map(fn ($code) => self::normalize((string) $code))
            ->unique()
            ->values()
            ->all();
    }

    public static function setCurrent(string $apiCode): void
    {
        Context::set(self::CONTEXT_KEY, self::normalize($apiCode));
    }

    public static function current(): string
    {
        $ctx = Context::get(self::CONTEXT_KEY);
        if (is_string($ctx) && $ctx !== '') {
            return $ctx;
        }
        return self::defaultApiLocale();
    }
}
