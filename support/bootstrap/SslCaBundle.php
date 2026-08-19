<?php

declare(strict_types=1);

namespace support\bootstrap;

use Webman\Bootstrap;

/**
 * 为 Windows/宝塔 PHP 补齐 CA 证书路径，避免 cURL error 60。
 */
class SslCaBundle implements Bootstrap
{
    public static function start($worker): void
    {
        $caFile = self::resolve();
        if ($caFile === null) {
            return;
        }

        @ini_set('curl.cainfo', $caFile);
        @ini_set('openssl.cafile', $caFile);
        putenv('CURL_CA_BUNDLE=' . $caFile);
        putenv('SSL_CERT_FILE=' . $caFile);
    }

    private static function resolve(): ?string
    {
        $candidates = [
            (string) env('CURL_CA_BUNDLE', ''),
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            runtime_path('ssl/cacert.pem'),
            'D:/BtSoft/php/82/extras/ssl/cacert.pem',
        ];

        foreach ($candidates as $path) {
            $path = trim(str_replace(["\r", "\n"], '', $path));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
