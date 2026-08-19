<?php

declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * 仿照 docs/reference/api/app/services/GoogleTranslate.php
 * 通过 Google 移动版翻译页拉取译文（无需 API Key）。
 */
class GoogleTranslate
{
    /**
     * @param  string  $source  源语言，如 zh-CN / en
     * @param  string  $target  目标语言，如 en / zh-CN
     * @param  string  $text    待翻译文本
     */
    public static function translate(string $source, string $target, string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $source = self::normalizeGoogleCode($source);
        $target = self::normalizeGoogleCode($target);
        if ($source === $target) {
            return $text;
        }

        $html = self::requestTranslation($source, $target, $text);
        $translation = self::getSentencesFromHtml($html);

        return html_entity_decode(trim($translation), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 项目语言码 → Google Translate 语言码。
     */
    public static function toGoogleCode(string $apiLocale): string
    {
        $code = strtolower(str_replace('_', '-', trim($apiLocale)));
        return match ($code) {
            'zh', 'zh-cn', 'cn' => 'zh-CN',
            'zh-tw' => 'zh-TW',
            'en', 'en-us', 'en-gb' => 'en',
            'ja', 'ja-jp' => 'ja',
            'ko', 'ko-kr' => 'ko',
            'id', 'id-id' => 'id',
            'ms', 'ms-my' => 'ms',
            'pt', 'pt-br' => 'pt',
            'es', 'es-es' => 'es',
            default => $code !== '' ? $code : 'en',
        };
    }

    public static function normalizeGoogleCode(string $code): string
    {
        return self::toGoogleCode($code);
    }

    public static function isChineseLocale(string $apiLocale): bool
    {
        $g = self::toGoogleCode($apiLocale);
        return $g === 'zh-CN' || $g === 'zh-TW';
    }

    protected static function requestTranslation(string $source, string $target, string $text): string
    {
        $url = 'https://translate.google.com/m?tl=' . rawurlencode($target)
            . '&sl=' . rawurlencode($source)
            . '&q=' . rawurlencode($text);

        $client = new Client([
            'timeout' => 20,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; LangTextBot/1.0)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
            'http_errors' => true,
        ]);

        try {
            $response = $client->request('GET', $url);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Google 翻译请求失败: ' . $e->getMessage(), 0, $e);
        }

        return (string) $response->getBody()->getContents();
    }

    protected static function getSentencesFromHtml(string $html): string
    {
        $pattern = '/<div class="result-container">(.*?)<\/div>/s';
        if (preg_match($pattern, $html, $matches)) {
            return (string) ($matches[1] ?? '');
        }

        return '';
    }
}
