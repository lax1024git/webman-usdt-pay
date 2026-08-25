<?php

declare(strict_types=1);

namespace app\support\pay;

final class PaySecretCipher
{
    public static function encrypt(string $plain): string
    {
        $key = hash('sha256', (string) env('JWT_SECRET', 'pay-secret'), true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('密钥加密失败');
        }

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $key = hash('sha256', (string) env('JWT_SECRET', 'pay-secret'), true);
        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        $plain = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? $plain : '';
    }
}
