<?php

declare(strict_types=1);

namespace app\support\chain;

use Elliptic\EC;

/**
 * TRON 交易 ECDSA 签名（secp256k1）。
 */
final class TronSigner
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * 对 txID（32 字节 hex）签名，返回 65 字节 hex 签名。
     */
    public function signTxId(string $txIdHex, string $privateKeyHex): string
    {
        $privateKeyHex = ltrim($privateKeyHex, '0x');
        if (strlen($privateKeyHex) !== 64) {
            throw new \InvalidArgumentException('私钥格式无效');
        }

        $txIdHex = ltrim($txIdHex, '0x');
        $key = $this->ec->keyFromPrivate($privateKeyHex, 'hex');
        $signature = $key->sign($txIdHex, 'hex', ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        // TRON 使用 recovery id，elliptic-php 的 recoveryParam 即 v
        $v = dechex($signature->recoveryParam ?? 0);

        return $r . $s . str_pad($v, 2, '0', STR_PAD_LEFT);
    }

    /** 由私钥推导 Base58 地址 */
    public function addressFromPrivateKey(string $privateKeyHex): string
    {
        $privateKeyHex = ltrim($privateKeyHex, '0x');
        $key = $this->ec->keyFromPrivate($privateKeyHex, 'hex');
        $pubKey = $key->getPublic(false, 'hex');
        // 去掉 04 前缀
        $pubKey = substr($pubKey, 2);
        $hash = Keccak::hash(hex2bin($pubKey), 256);
        $addressBytes = "\x41" . substr($hash, -20);

        return TronAddress::encodeFromHex(bin2hex($addressBytes));
    }
}

/**
 * Keccak-256（kornrunner/keccak 命名空间兼容包装）。
 */
final class Keccak
{
    public static function hash(string $data, int $bits): string
    {
        return \kornrunner\Keccak::hash($data, $bits);
    }
}
