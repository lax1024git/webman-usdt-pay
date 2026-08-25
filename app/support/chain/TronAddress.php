<?php

declare(strict_types=1);

namespace app\support\chain;

/**
 * TRON Base58Check 地址编解码（无第三方 TRON SDK 依赖）。
 */
final class TronAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function isValid(string $address): bool
    {
        return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }

    /** Base58Check -> 21 字节 hex（含 41 前缀） */
    public static function decodeToHex(string $address): string
    {
        $decoded = self::base58DecodeCheck($address);
        if ($decoded === '') {
            throw new \InvalidArgumentException('Invalid TRON address: ' . $address);
        }

        return bin2hex($decoded);
    }

    /** 21 字节 hex（含 41 前缀）-> Base58Check */
    public static function encodeFromHex(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if (strlen($hex) !== 42 || !str_starts_with($hex, '41')) {
            throw new \InvalidArgumentException('Invalid TRON hex address');
        }

        return self::base58EncodeCheck(hex2bin($hex));
    }

    /** 编码 transfer(address,uint256) 的 address 参数（32 字节 hex，无 0x） */
    public static function encodeAddressParam(string $base58Address): string
    {
        $hex = self::decodeToHex($base58Address);
        // 去掉 41 前缀，左补零至 64 字符
        return str_pad(substr($hex, 2), 64, '0', STR_PAD_LEFT);
    }

    private static function base58DecodeCheck(string $input): string
    {
        $num = gmp_init(0);
        foreach (str_split($input) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                return '';
            }
            $num = gmp_add(gmp_mul($num, 58), $pos);
        }

        $hex = gmp_strval($num, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $leadingOnes = strspn($input, '1');
        $decoded = hex2bin($hex) ?: '';
        $decoded = str_repeat("\x00", $leadingOnes) . $decoded;

        if (strlen($decoded) < 5) {
            return '';
        }
        $payload = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);
        $hash = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        if (!hash_equals($checksum, $hash)) {
            return '';
        }

        return $payload;
    }

    private static function base58EncodeCheck(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        $data = $payload . $checksum;
        $num = gmp_init(bin2hex($data), 16);
        $encoded = '';
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, 58);
            $encoded = self::ALPHABET[gmp_intval($rem)] . $encoded;
        }
        $leadingZeros = strspn($data, "\x00");

        return str_repeat('1', $leadingZeros) . $encoded;
    }
}
