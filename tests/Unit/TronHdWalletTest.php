<?php

declare(strict_types=1);

namespace tests\Unit;

use app\support\chain\TronAddress;
use app\support\chain\TronHdWallet;
use PHPUnit\Framework\TestCase;

class TronHdWalletTest extends TestCase
{
    /** @var array<string, string> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (['TRON_HD_MNEMONIC', 'TRON_HD_PASSPHRASE', 'TRON_HD_SEED'] as $key) {
            $val = getenv($key);
            $this->envBackup[$key] = $val === false ? null : $val;
        }
        putenv('TRON_HD_MNEMONIC');
        putenv('TRON_HD_PASSPHRASE');
        putenv('TRON_HD_SEED');
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $val) {
            if ($val === null) {
                putenv($key);
            } else {
                putenv($key . '=' . $val);
            }
        }
    }

    public function testIsConfiguredFalseWhenEmpty(): void
    {
        $this->assertFalse(TronHdWallet::isConfigured());
    }

    public function testDeriveFromSeedProducesValidTronAddress(): void
    {
        $seed = str_repeat('ab', 32);
        putenv('TRON_HD_SEED=' . $seed);

        $this->assertTrue(TronHdWallet::isConfigured());

        $a = TronHdWallet::derive(1);
        $b = TronHdWallet::derive(2);

        $this->assertSame(1, $a['index']);
        $this->assertSame(64, strlen($a['private_key']));
        $this->assertTrue(TronAddress::isValid($a['address']));
        $this->assertNotSame($a['address'], $b['address']);
        $this->assertSame($a, TronHdWallet::derive(1));
    }
}
