<?php

declare(strict_types=1);

namespace app\support\pay;

/**
 * 热钱包配置读取（私钥 AES 加密存储于 .env）。
 */
final class HotWalletConfig
{
    public static function address(): string
    {
        return trim((string) env('TRON_HOT_WALLET_ADDRESS', ''));
    }

    public static function privateKey(): string
    {
        $encrypted = trim((string) env('TRON_HOT_WALLET_PRIVATE_KEY_ENCRYPTED', ''));
        if ($encrypted === '') {
            // 开发环境可明文配置（生产务必加密）
            return trim((string) env('TRON_HOT_WALLET_PRIVATE_KEY', ''));
        }

        return PaySecretCipher::decrypt($encrypted);
    }

    public static function isConfigured(): bool
    {
        return self::address() !== '' && self::privateKey() !== '';
    }
}
