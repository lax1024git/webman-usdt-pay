<?php

declare(strict_types=1);

namespace app\support\chain;

use Elliptic\EC;

/**
 * TRON BIP44 派生：m/44'/195'/0'/0/{index}
 */
final class TronHdWallet
{
    private const CURVE_N = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';

    public static function isConfigured(): bool
    {
        return self::seedBytes() !== '';
    }

    /**
     * @return array{index:int,private_key:string,address:string}
     */
    public static function derive(int $index): array
    {
        $index = max(0, $index);
        $node = self::masterNode();
        foreach ([
            44 | 0x80000000,
            195 | 0x80000000,
            0 | 0x80000000,
            0,
            $index,
        ] as $child) {
            $node = self::ckdPriv($node, $child);
        }

        $privateKey = $node['k'];
        $address = (new TronSigner())->addressFromPrivateKey($privateKey);

        return [
            'index' => $index,
            'private_key' => $privateKey,
            'address' => $address,
        ];
    }

    private static function seedBytes(): string
    {
        $hex = strtolower(self::readEnv('TRON_HD_SEED'));
        if ($hex !== '' && ctype_xdigit($hex)) {
            if (strlen($hex) % 2 === 1) {
                $hex = '0' . $hex;
            }
            $bin = hex2bin($hex);

            return $bin !== false ? $bin : '';
        }

        $mnemonic = self::readEnv('TRON_HD_MNEMONIC');
        if ($mnemonic === '') {
            return '';
        }
        $passphrase = self::readEnv('TRON_HD_PASSPHRASE');

        return hash_pbkdf2('sha512', $mnemonic, 'mnemonic' . $passphrase, 2048, 64, true);
    }

    private static function readEnv(string $key): string
    {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return trim((string) $val);
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return trim((string) $_ENV[$key]);
        }

        return trim((string) (function_exists('env') ? env($key, '') : ''));
    }

    /** @return array{k:string,c:string} k=hex priv, c=raw chain code */
    private static function masterNode(): array
    {
        $seed = self::seedBytes();
        if ($seed === '') {
            throw new \RuntimeException('未配置 TRON_HD_MNEMONIC 或 TRON_HD_SEED');
        }
        $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);

        return [
            'k' => bin2hex(substr($I, 0, 32)),
            'c' => substr($I, 32, 32),
        ];
    }

    /**
     * @param array{k:string,c:string} $node
     * @return array{k:string,c:string}
     */
    private static function ckdPriv(array $node, int $i): array
    {
        $kpar = str_pad($node['k'], 64, '0', STR_PAD_LEFT);
        $cpar = $node['c'];
        $indexBin = pack('N', $i);

        if (($i & 0x80000000) !== 0) {
            $data = "\x00" . hex2bin($kpar) . $indexBin;
        } else {
            $data = hex2bin(self::compressedPubHex($kpar)) . $indexBin;
        }

        $I = hash_hmac('sha512', $data, $cpar, true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 32);
        $n = gmp_init(self::CURVE_N, 16);
        $ki = gmp_mod(gmp_add(gmp_init(bin2hex($IL), 16), gmp_init($kpar, 16)), $n);
        if (gmp_cmp($ki, 0) === 0) {
            throw new \RuntimeException('HD 派生得到无效私钥');
        }

        return [
            'k' => str_pad(gmp_strval($ki, 16), 64, '0', STR_PAD_LEFT),
            'c' => $IR,
        ];
    }

    private static function compressedPubHex(string $privateKeyHex): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKeyHex, 'hex');

        return $key->getPublic(true, 'hex');
    }
}
